<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';
campus_require_api_role('admin');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') campus_json(['error' => 'Unsupported request.'], 405);
campus_require_csrf();
$input = json_decode(file_get_contents('php://input') ?: '', true);
$claimId = filter_var($input['claimId'] ?? null, FILTER_VALIDATE_INT);
$status = $input['status'] ?? '';
if (!$claimId || !in_array($status, ['approved', 'rejected'], true)) campus_json(['error' => 'Choose a valid claim decision.'], 422);
$statement = campus_db()->prepare("UPDATE claims SET status = :status WHERE id = :id AND status IN ('pending', 'processing')");
$statement->execute(['status' => $status, 'id' => $claimId]);
if ($statement->rowCount() !== 1) campus_json(['error' => 'This claim has already been reviewed or is unavailable.'], 409);
campus_json(['message' => $status === 'approved' ? 'Claim approved.' : 'Claim rejected.']);
