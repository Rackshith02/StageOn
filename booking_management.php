<?php
/**
 * Project File Purpose:
 * - booking_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

// Guard admin-only access before review actions.
adminRequireLogin();
global $conn;

// Handle booking approval/rejection submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
    if ($bookingId > 0 && in_array($decision, ['approved', 'rejected'], true)) {
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        reviewEventBooking($conn, $bookingId, $adminId, $decision, $reviewNote);
    }
}

// Load booking requests for admin review table.
$bookings = fetchEventBookingsForAdmin($conn, 120);
renderAdminPageStart('bookings', 'Booking Management');
?>
<!-- Section: Event Booking Review Table -->
<section class="card panel">
  <div class="panel-head">
    <h3>Event Booking Requests</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>User</th>
        <th>Type</th>
        <th>Event</th>
        <th>Date</th>
        <th>Location</th>
        <th>Budget</th>
        <th>Status</th>
        <th>Email</th>
        <th>Time</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="10">No booking requests yet.</td></tr>
      <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
          <tr>
            <td><?= htmlspecialchars((string) $booking['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $booking['requested_by_type']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $booking['event_title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $booking['event_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $booking['location'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($booking['budget'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $booking['status']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $booking['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $booking['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                <input type="text" name="review_note" placeholder="Note" style="max-width:110px;">
                <button type="submit" name="decision" value="approved">Approve</button>
                <button type="submit" name="decision" value="rejected">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php renderAdminPageEnd(); ?>


