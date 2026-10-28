<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';

try {
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && $action === 'current') {
        $user = campus_current_user();
        campus_json([
            'user' => $user ? campus_public_user($user) : null,
            'csrfToken' => campus_csrf_token(),
        ]);
    }

    if ($method === 'GET' && $action === 'browse') {
        campus_require_api_roles(['student', 'admin']);
        $type = campus_input_text($_GET, 'type');
        $query = trim(campus_input_text($_GET, 'q'));
        if ($type !== '' && !in_array($type, ['lost', 'found'], true)) {
            campus_json(['error' => 'Choose lost or found items.'], 422);
        }
        $filters = ["i.status = 'published'"];
        $parameters = [];
        if ($type !== '') {
            $filters[] = 'i.item_type = :type';
            $parameters['type'] = $type;
        }
        if ($query !== '') {
            $filters[] = '(i.title LIKE :query OR i.category LIKE :query OR i.description LIKE :query OR i.location LIKE :query)';
            $parameters['query'] = '%' . $query . '%';
        }
        $statement = campus_db()->prepare(
            'SELECT i.id, i.item_type, i.title, i.category, i.description, i.location, i.incident_date,
                    i.status, i.created_at, u.first_name AS reporter_first_name, u.last_name AS reporter_last_name,
                    EXISTS (SELECT 1 FROM item_images im WHERE im.item_id = i.id) AS has_image,
                    (SELECT COUNT(*) FROM claims c WHERE c.item_id = i.id AND c.status = \'pending\') AS pending_claims
             FROM items i JOIN users u ON u.id = i.reporter_id
             WHERE ' . implode(' AND ', $filters) . '
             ORDER BY i.created_at DESC, i.id DESC LIMIT 100'
        );
        $statement->execute($parameters);
        campus_json(['items' => $statement->fetchAll()]);
    }

    if ($method === 'GET' && $action === 'item') {
        $viewer = campus_require_api_roles(['student', 'admin']);
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            campus_json(['error' => 'Choose a valid item.'], 422);
        }
        $statement = campus_db()->prepare(
            'SELECT i.id, i.item_type, i.title, i.category, i.description, i.location, i.incident_date,
                    i.status, i.created_at, u.first_name AS reporter_first_name, u.last_name AS reporter_last_name,
                    i.reporter_id = :viewer_owner AS is_mine, EXISTS (SELECT 1 FROM item_images im WHERE im.item_id = i.id) AS has_image,
                    (SELECT COUNT(*) FROM claims c WHERE c.item_id = i.id AND c.status = \'pending\') AS pending_claims,
                    (SELECT status FROM claims c WHERE c.item_id = i.id AND c.claimant_id = :viewer_id LIMIT 1) AS my_claim_status
             FROM items i JOIN users u ON u.id = i.reporter_id
             WHERE i.id = :id AND (
                 i.status = \'published\' OR i.reporter_id = :owner_id
                 OR EXISTS (SELECT 1 FROM claims visible_claim WHERE visible_claim.item_id = i.id AND visible_claim.claimant_id = :claim_owner)
                 OR :viewer_is_admin = 1
             )'
        );
        $statement->execute([
            'viewer_id' => $viewer['id'],
            'viewer_owner' => $viewer['id'],
            'id' => $id,
            'owner_id' => $viewer['id'],
            'claim_owner' => $viewer['id'],
            'viewer_is_admin' => $viewer['role'] === 'admin' ? 1 : 0,
        ]);
        $item = $statement->fetch();
        if (!$item) {
            campus_json(['error' => 'This item is unavailable.'], 404);
        }
        $isMine = (bool) $item['is_mine'];
        $item['can_claim'] = $item['item_type'] === 'found'
            && $item['status'] === 'published'
            && $viewer['role'] === 'student'
            && !$isMine
            && $item['my_claim_status'] === null;
        campus_json(['item' => $item]);
    }

    if ($method === 'GET' && $action === 'my-items') {
        $user = campus_require_api_role('student');
        $database = campus_db();
        $reports = $database->prepare(
            'SELECT i.id, i.item_type, i.title, i.category, i.location, i.incident_date, i.status, i.created_at,
                    EXISTS (SELECT 1 FROM item_images im WHERE im.item_id = i.id) AS has_image
             FROM items i WHERE i.reporter_id = :user_id ORDER BY i.created_at DESC, i.id DESC'
        );
        $reports->execute(['user_id' => $user['id']]);
        $claims = $database->prepare(
            'SELECT c.id AS claim_id, c.details, c.status AS claim_status, c.created_at AS claimed_at,
                    i.id AS item_id, i.title, i.item_type, i.location, i.status AS item_status,
                    EXISTS (SELECT 1 FROM item_images im WHERE im.item_id = i.id) AS has_image
             FROM claims c JOIN items i ON i.id = c.item_id
             WHERE c.claimant_id = :user_id ORDER BY c.created_at DESC, c.id DESC'
        );
        $claims->execute(['user_id' => $user['id']]);
        campus_json(['reports' => $reports->fetchAll(), 'claims' => $claims->fetchAll()]);
    }

    if ($method === 'GET' && $action === 'image') {
        $viewer = campus_current_user();
        if (!$viewer) {
            campus_json(['error' => 'Please log in to continue.'], 401);
        }
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            campus_json(['error' => 'Choose a valid item image.'], 422);
        }
        $statement = campus_db()->prepare(
            "SELECT im.mime_type, im.image_data, i.reporter_id, i.status FROM item_images im
             JOIN items i ON i.id = im.item_id WHERE i.id = :id"
        );
        $statement->execute(['id' => $id]);
        $image = $statement->fetch();
        if (
            !$image
            || !in_array($image['status'], ['published', 'resolved'], true)
                && (int) $image['reporter_id'] !== (int) $viewer['id']
                && $viewer['role'] !== 'admin'
        ) {
            campus_json(['error' => 'This item image is unavailable.'], 404);
        }
        header('Content-Type: ' . $image['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        echo $image['image_data'];
        exit;
    }

    if ($method === 'GET' && $action === 'admin-monthly-report') {
        campus_require_api_role('admin');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $monthlyReport = campus_db()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM items WHERE created_at >= :month_start AND created_at <= :month_end) AS reports_total,
                (SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'published' AND created_at >= :month_start AND created_at <= :month_end) AS found_published,
                (SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'published' AND created_at >= :month_start AND created_at <= :month_end) AS lost_published,
                (SELECT COUNT(*) FROM items WHERE status = 'pending' AND created_at >= :month_start AND created_at <= :month_end) AS pending_reports,
                (SELECT COUNT(*) FROM items WHERE status = 'rejected' AND created_at >= :month_start AND created_at <= :month_end) AS rejected_reports,
                (SELECT COUNT(*) FROM claims WHERE created_at >= :month_start AND created_at <= :month_end) AS claims_total,
                (SELECT COUNT(*) FROM claims WHERE status = 'approved' AND created_at >= :month_start AND created_at <= :month_end) AS approved_claims,
                (SELECT COUNT(*) FROM claims WHERE status = 'rejected' AND created_at >= :month_start AND created_at <= :month_end) AS rejected_claims,
                (SELECT COUNT(*) FROM users WHERE created_at >= :month_start AND created_at <= :month_end AND role = 'student') AS new_students"
        );
        $monthlyReport->execute([
            'month_start' => $monthStart . ' 00:00:00',
            'month_end' => $monthEnd . ' 23:59:59',
        ]);
        $monthly = $monthlyReport->fetch();
        campus_json([
            'monthly' => [
                'period' => date('F Y'),
                'reports_total' => (int) ($monthly['reports_total'] ?? 0),
                'found_published' => (int) ($monthly['found_published'] ?? 0),
                'lost_published' => (int) ($monthly['lost_published'] ?? 0),
                'pending_reports' => (int) ($monthly['pending_reports'] ?? 0),
                'rejected_reports' => (int) ($monthly['rejected_reports'] ?? 0),
                'claims_total' => (int) ($monthly['claims_total'] ?? 0),
                'approved_claims' => (int) ($monthly['approved_claims'] ?? 0),
                'rejected_claims' => (int) ($monthly['rejected_claims'] ?? 0),
                'new_students' => (int) ($monthly['new_students'] ?? 0),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'admin-summary') {
        campus_require_api_role('admin');
        $database = campus_db();
        $users = $database->query(
            "SELECT id, first_name, last_name, student_number, program, email, role, created_at
             FROM users ORDER BY created_at DESC, id DESC"
        )->fetchAll();
        $counts = $database->query(
            "SELECT role, COUNT(*) AS total FROM users GROUP BY role"
        )->fetchAll();
        $summary = ['student' => 0, 'admin' => 0];
        foreach ($counts as $count) {
            $summary[$count['role']] = (int) $count['total'];
        }
        $itemSummary = $database->query(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN item_type = 'found' AND status = 'published' THEN 1 ELSE 0 END) AS found,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN item_type = 'lost' THEN 1 ELSE 0 END) AS lost_reports
             FROM items"
        )->fetch();
        campus_json([
            'summary' => $summary,
            'users' => $users,
            'items' => [
                'pending' => (int) ($itemSummary['pending'] ?? 0),
                'found' => (int) ($itemSummary['found'] ?? 0),
                'resolved' => (int) ($itemSummary['resolved'] ?? 0),
                'lostReports' => (int) ($itemSummary['lost_reports'] ?? 0),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'admin-items') {
        campus_require_api_role('admin');
        $items = campus_db()->query(
            'SELECT i.id, i.item_type, i.title, i.category, i.description, i.location, i.incident_date,
                    i.status, i.created_at, u.first_name AS reporter_first_name, u.last_name AS reporter_last_name,
                    u.student_number, u.email AS reporter_email,
                    EXISTS (SELECT 1 FROM item_images im WHERE im.item_id = i.id) AS has_image
             FROM items i JOIN users u ON u.id = i.reporter_id
             ORDER BY CASE i.status WHEN \'pending\' THEN 0 ELSE 1 END, i.created_at DESC, i.id DESC
             LIMIT 100'
        )->fetchAll();
        campus_json(['items' => $items]);
    }

    if ($method === 'GET' && $action === 'admin-claims') {
        campus_require_api_role('admin');
        $claims = campus_db()->query(
            'SELECT c.id, c.details, c.status, c.created_at,
                    i.id AS item_id, i.title AS item_title, i.item_type, i.status AS item_status,
                    claimant.first_name, claimant.last_name, claimant.student_number, claimant.email,
                    owner.first_name AS owner_first_name, owner.last_name AS owner_last_name
             FROM claims c JOIN items i ON i.id = c.item_id
             JOIN users claimant ON claimant.id = c.claimant_id
             JOIN users owner ON owner.id = i.reporter_id
             ORDER BY CASE c.status WHEN \'pending\' THEN 0 ELSE 1 END, c.created_at DESC
             LIMIT 100'
        )->fetchAll();
        campus_json(['claims' => $claims]);
    }

    if ($method !== 'POST') {
        campus_json(['error' => 'Unsupported request.'], 405);
    }

    campus_require_csrf();
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        campus_json(['message' => 'You have been logged out.']);
    }

    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) {
        campus_json(['error' => 'Invalid request data.'], 400);
    }

    if ($action === 'login') {
        $email = strtolower(trim(campus_input_text($input, 'email')));
        $password = campus_input_text($input, 'password');
        $statement = campus_db()->prepare('SELECT * FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            campus_json(['error' => 'Email or password is incorrect.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        campus_json([
            'user' => campus_public_user($user),
            'csrfToken' => $_SESSION['csrf_token'],
        ]);
    }

    if ($action === 'change-password') {
        $user = campus_require_api_role('student');
        $currentPassword = campus_input_text($input, 'currentPassword');
        $newPassword = campus_input_text($input, 'newPassword');
        $confirmPassword = campus_input_text($input, 'confirmPassword');

        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            campus_json(['error' => 'Your new password must be between 8 and 72 characters.'], 422);
        }
        if ($newPassword !== $confirmPassword) {
            campus_json(['error' => 'The new passwords do not match.'], 422);
        }
        $passwordQuery = campus_db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $passwordQuery->execute(['id' => $user['id']]);
        $passwordHash = $passwordQuery->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            campus_json(['error' => 'Your current password is incorrect.'], 422);
        }

        $statement = campus_db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $statement->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
        campus_json(['message' => 'Your password has been changed successfully.']);
    }

    if ($action === 'register') {
        $profile = [
            'first_name' => trim(campus_input_text($input, 'firstName')),
            'last_name' => trim(campus_input_text($input, 'lastName')),
            'student_number' => trim(campus_input_text($input, 'studentNumber')),
            'program' => trim(campus_input_text($input, 'program')),
            'email' => strtolower(trim(campus_input_text($input, 'email'))),
        ];
        $password = campus_input_text($input, 'password');

        if (
            in_array('', $profile, true) ||
            !filter_var($profile['email'], FILTER_VALIDATE_EMAIL) ||
            !str_ends_with($profile['email'], '@cvsu.edu.ph') ||
            strlen($password) < 8 ||
            strlen($password) > 72
        ) {
            campus_json(['error' => 'Enter valid student details and a password between 8 and 72 characters.'], 422);
        }

        $statement = campus_db()->prepare(
            'INSERT INTO users (first_name, last_name, student_number, program, email, password_hash, role)
             VALUES (:first_name, :last_name, :student_number, :program, :email, :password_hash, \'student\')'
        );
        try {
            $statement->execute($profile + ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                campus_json(['error' => 'An account already exists with this campus email or student number.'], 409);
            }
            throw $error;
        }

        campus_json(['message' => 'Your student account has been created. You can now log in.'], 201);
    }

    if ($action === 'report-item') {
        $user = campus_require_api_roles(['admin']);
        $type = campus_input_text($input, 'itemType');
        $title = trim(campus_input_text($input, 'title'));
        $category = trim(campus_input_text($input, 'category'));
        $description = trim(campus_input_text($input, 'description'));
        $location = trim(campus_input_text($input, 'location'));
        $incidentDate = campus_input_text($input, 'incidentDate');
        $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $incidentDate);
        $categories = ['Electronics', 'ID / Cards', 'Books & School Supplies', 'Personal Items', 'Clothing & Accessories', 'Keys', 'Other'];
        if (
            !in_array($type, ['lost', 'found'], true)
            || strlen($title) < 3 || strlen($title) > 120
            || !in_array($category, $categories, true)
            || strlen($description) < 15 || strlen($description) > 2000
            || $location === '' || strlen($location) > 160
            || !$validDate || $validDate->format('Y-m-d') !== $incidentDate
            || $incidentDate > gmdate('Y-m-d')
        ) {
            campus_json(['error' => 'Fill in each field with valid lost/found item details.'], 422);
        }

        $imageData = null;
        $imageMime = null;
        $imageDataUrl = $input['imageData'] ?? '';
        if ($imageDataUrl !== '') {
            if (!is_string($imageDataUrl) || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $imageDataUrl, $matches)) {
                campus_json(['error' => 'Upload a JPEG, PNG, or WebP image.'], 422);
            }
            $imageData = base64_decode($matches[2], true);
            if ($imageData === false || strlen($imageData) > 3 * 1024 * 1024) {
                campus_json(['error' => 'Images must be no larger than 3 MB.'], 422);
            }
            $imageInfo = @getimagesizefromstring($imageData);
            $imageMime = $imageInfo['mime'] ?? '';
            if (
                !$imageInfo
                || !in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || $imageMime !== ('image/' . $matches[1])
                || $imageInfo[0] > 5000 || $imageInfo[1] > 5000
            ) {
                campus_json(['error' => 'The uploaded file is not a supported image.'], 422);
            }
        }

        $database = campus_db();
        $database->beginTransaction();
        try {
            $statement = $database->prepare(
                "INSERT INTO items (reporter_id, item_type, title, category, description, location, incident_date)
                 VALUES (:reporter_id, :item_type, :title, :category, :description, :location, :incident_date)"
            );
            $statement->execute([
                'reporter_id' => $user['id'],
                'item_type' => $type,
                'title' => $title,
                'category' => $category,
                'description' => $description,
                'location' => $location,
                'incident_date' => $incidentDate,
            ]);
            $itemId = (int) $database->lastInsertId();
            if ($imageData !== null) {
                $imageStatement = $database->prepare(
                    'INSERT INTO item_images (item_id, mime_type, image_data) VALUES (:item_id, :mime_type, :image_data)'
                );
                $imageStatement->bindValue(':item_id', $itemId, PDO::PARAM_INT);
                $imageStatement->bindValue(':mime_type', $imageMime, PDO::PARAM_STR);
                $imageStatement->bindValue(':image_data', $imageData, PDO::PARAM_LOB);
                $imageStatement->execute();
            }
            $database->commit();
        } catch (Throwable $error) {
            $database->rollBack();
            throw $error;
        }
        campus_json(['message' => 'Your report was submitted for School Admin review.', 'itemId' => $itemId], 201);
    }

    if ($action === 'claim-item') {
        $user = campus_require_api_role('student');
        $itemId = filter_var($input['itemId'] ?? null, FILTER_VALIDATE_INT);
        $details = trim(campus_input_text($input, 'details'));
        if (!$itemId || strlen($details) < 15 || strlen($details) > 1500) {
            campus_json(['error' => 'Choose an item and describe proof of ownership (15–1,500 characters).'], 422);
        }
        $database = campus_db();
        $itemStatement = $database->prepare(
            "SELECT reporter_id FROM items WHERE id = :id AND item_type = 'found' AND status = 'published'"
        );
        $itemStatement->execute(['id' => $itemId]);
        $item = $itemStatement->fetch();
        if (!$item) {
            campus_json(['error' => 'This found item is no longer available for a claim.'], 404);
        }
        if ((int) $item['reporter_id'] === (int) $user['id']) {
            campus_json(['error' => 'You cannot submit a claim on your own item report.'], 403);
        }
        try {
            $statement = $database->prepare(
                "INSERT INTO claims (item_id, claimant_id, details) VALUES (:item_id, :claimant_id, :details)"
            );
            $statement->execute(['item_id' => $itemId, 'claimant_id' => $user['id'], 'details' => $details]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                campus_json(['error' => 'You already submitted a claim for this item.'], 409);
            }
            throw $error;
        }
        campus_json(['message' => 'Your ownership claim was sent privately to School Admin for review.'], 201);
    }

    if ($action === 'profile') {
        $user = campus_require_api_role('student');
        $profile = [
            'first_name' => trim(campus_input_text($input, 'firstName')),
            'last_name' => trim(campus_input_text($input, 'lastName')),
            'student_number' => trim(campus_input_text($input, 'studentNumber')),
            'program' => trim(campus_input_text($input, 'program')),
            'email' => strtolower(trim(campus_input_text($input, 'email'))),
        ];
        if (
            in_array('', $profile, true) ||
            !filter_var($profile['email'], FILTER_VALIDATE_EMAIL) ||
            !str_ends_with($profile['email'], '@cvsu.edu.ph')
        ) {
            campus_json(['error' => 'Enter valid student details and a CvSU campus email.'], 422);
        }

        $statement = campus_db()->prepare(
            'UPDATE users
             SET first_name = :first_name, last_name = :last_name,
                 student_number = :student_number, program = :program, email = :email
             WHERE id = :id'
        );
        try {
            $statement->execute($profile + ['id' => $user['id']]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                campus_json(['error' => 'That campus email or student number is already in use.'], 409);
            }
            throw $error;
        }

        campus_json(['user' => campus_public_user(campus_current_user())]);
    }

    if ($action === 'admin-item-status') {
        campus_require_api_role('admin');
        $itemId = filter_var($input['itemId'] ?? null, FILTER_VALIDATE_INT);
        $status = campus_input_text($input, 'status');
        if (!$itemId || !in_array($status, ['published', 'rejected'], true)) {
            campus_json(['error' => 'Choose a valid report and review decision.'], 422);
        }
        $statement = campus_db()->prepare(
            "UPDATE items SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute(['status' => $status, 'id' => $itemId]);
        if ($statement->rowCount() !== 1) {
            campus_json(['error' => 'This report has already been reviewed or is no longer available.'], 409);
        }
        campus_json(['message' => $status === 'published' ? 'Report published.' : 'Report rejected.']);
    }

    if ($action === 'admin-claim-status') {
        campus_require_api_role('admin');
        $claimId = filter_var($input['claimId'] ?? null, FILTER_VALIDATE_INT);
        $status = campus_input_text($input, 'status');
        if (!$claimId || !in_array($status, ['approved', 'rejected'], true)) {
            campus_json(['error' => 'Choose a valid claim and review decision.'], 422);
        }
        $database = campus_db();
        $database->beginTransaction();
        try {
            $claimStatement = $database->prepare(
                "SELECT c.item_id, i.status AS item_status
                 FROM claims c JOIN items i ON i.id = c.item_id
                 WHERE c.id = :id AND c.status = 'pending'"
            );
            $claimStatement->execute(['id' => $claimId]);
            $claim = $claimStatement->fetch();
            if (!$claim || $claim['item_status'] !== 'published') {
                $database->rollBack();
                campus_json(['error' => 'This claim has already been reviewed or its item is unavailable.'], 409);
            }
            $updateClaim = $database->prepare(
                'UPDATE claims SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $updateClaim->execute(['status' => $status, 'id' => $claimId]);
            if ($status === 'approved') {
                $otherClaims = $database->prepare(
                    "UPDATE claims SET status = 'rejected', updated_at = CURRENT_TIMESTAMP
                     WHERE item_id = :item_id AND id <> :claim_id AND status = 'pending'"
                );
                $otherClaims->execute(['item_id' => $claim['item_id'], 'claim_id' => $claimId]);
                $resolveItem = $database->prepare(
                    "UPDATE items SET status = 'resolved', updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND status = 'published'"
                );
                $resolveItem->execute(['id' => $claim['item_id']]);
            }
            $database->commit();
        } catch (Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        campus_json(['message' => $status === 'approved' ? 'Claim approved and item resolved.' : 'Claim rejected.']);
    }

    if ($action === 'admin-user-role') {
        $admin = campus_require_api_role('admin');
        $userId = filter_var($input['userId'] ?? null, FILTER_VALIDATE_INT);
        $role = campus_input_text($input, 'role');
        if (!$userId || !in_array($role, ['student', 'admin'], true)) {
            campus_json(['error' => 'Choose a valid account and role.'], 422);
        }
        if ($userId === (int) $admin['id']) {
            campus_json(['error' => 'You cannot change your own account role.'], 409);
        }

        $database = campus_db();
        $database->beginTransaction();
        try {
            $accountQuery = $database->prepare('SELECT role FROM users WHERE id = :id');
            $accountQuery->execute(['id' => $userId]);
            $account = $accountQuery->fetch();
            if (!$account) {
                $database->rollBack();
                campus_json(['error' => 'This account could not be found.'], 404);
            }
            if ($account['role'] === $role) {
                $database->commit();
                campus_json(['message' => 'This account already has that role.']);
            }
            if ($account['role'] === 'admin' && $role === 'student') {
                $adminCount = (int) $database->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount <= 1) {
                    $database->rollBack();
                    campus_json(['error' => 'At least one School Admin account must remain.'], 409);
                }
            }

            $updateRole = $database->prepare('UPDATE users SET role = :role WHERE id = :id');
            $updateRole->execute(['role' => $role, 'id' => $userId]);
            $database->commit();
        } catch (Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        campus_json(['message' => 'Account role updated.', 'role' => $role]);
    }

    campus_json(['error' => 'Unknown request.'], 404);
} catch (Throwable $error) {
    error_log('Campus Finds API error: ' . $error->getMessage());
    campus_json(['error' => 'The server could not complete your request. Check the server logs and try again.'], 500);
}
