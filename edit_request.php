<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Edit Request';
$basePath = '';

$reference_no = trim($_GET['reference_no'] ?? $_POST['reference_no'] ?? '');
$student_number = trim($_GET['student_number'] ?? $_POST['student_number'] ?? '');

if ($reference_no === '' || $student_number === '') {
    header('Location: track.php');
    exit;
}

$clientIp = getClientIp();
$rateLimited = !checkRateLimit($conn, $clientIp);
$errors = [];
$result = null;

if (!$rateLimited) {
    recordLookupAttempt($conn, $clientIp);

    $stmt = $conn->prepare("SELECT * FROM requests WHERE reference_no = ? AND student_number = ?");
    $stmt->bind_param('ss', $reference_no, $student_number);
    $stmt->execute();
    $res = $stmt->get_result();
    $result = $res->num_rows === 1 ? $res->fetch_assoc() : null;
    $stmt->close();
}

$canEdit = $result && $result['request_status'] === 'Pending';

/* ---------------------------------------------------------------
   POST — validate and save changes
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $full_name     = trim($_POST['full_name'] ?? '');
    $course        = trim($_POST['course'] ?? '');
    $year_level    = trim($_POST['year_level'] ?? '');
    $semester      = trim($_POST['semester'] ?? '');
    $document_type = trim($_POST['document_type'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');

    $academicDocs = ['Certificate of Registration', 'Certificate of Grades'];
    $allowedDocs  = ['Certificate of Registration', 'Certificate of Grades', 'Diploma (Copy / Authentication)'];

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($course === '') $errors[] = 'Course/Program is required.';

    if ($document_type === '') {
        $errors[] = 'Document type is required.';
    } elseif (!in_array($document_type, $allowedDocs, true)) {
        $errors[] = 'That document type is not currently offered.';
    }

    if (in_array($document_type, $academicDocs, true)) {
        if ($year_level === '') $errors[] = 'Year level is required for this document type.';
        if ($semester === '')   $errors[] = 'Semester is required for this document type.';
    } else {
        $year_level = '';
        $semester   = '';
    }

    if ($purpose === '') $errors[] = 'Purpose is required.';

    if (empty($errors)) {
        $update = $conn->prepare(
            "UPDATE requests
                SET full_name = ?, course = ?, year_level = ?, semester = ?, document_type = ?, purpose = ?
              WHERE id = ? AND request_status = 'Pending'"
        );
        $update->bind_param(
            'ssssssi',
            $full_name,
            $course,
            $year_level,
            $semester,
            $document_type,
            $purpose,
            $result['id']
        );
        $update->execute();
        $update->close();

        logAudit($conn, $result, 'edited', $result['request_status'], $result['request_status']);

        header('Location: track.php?reference_no=' . urlencode($reference_no)
            . '&student_number=' . urlencode($student_number) . '&updated=1');
        exit;
    }

    // Re-render with the submitted (not the stale DB) values on error.
    $result['full_name']     = $full_name;
    $result['course']        = $course;
    $result['year_level']    = $year_level;
    $result['semester']      = $semester;
    $result['document_type'] = $document_type;
    $result['purpose']       = $purpose;
}

$academicDocs = ['Certificate of Registration', 'Certificate of Grades'];

function optionSelected(string $value, string $current): string
{
    return $value === $current ? ' selected' : '';
}

include 'includes/header.php';
?>

<section class="form-section">
    <?php if ($rateLimited): ?>
        <h1>Too Many Attempts</h1>
        <div class="alert alert-error">Too many lookup attempts from this connection. Please wait a few minutes and try again.</div>
        <a href="track.php" class="btn btn-secondary">Back to Track Request</a>
    <?php elseif (!$result): ?>
        <h1>Request Not Found</h1>
        <div class="alert alert-error">No matching request found. Please check your reference number and student number.</div>
        <a href="track.php" class="btn btn-secondary">Back to Track Request</a>
    <?php elseif (!$canEdit): ?>
        <h1>This Request Can No Longer Be Edited</h1>
        <div class="alert alert-error">Only requests that are still Pending can be edited. Your request is currently <strong><?php echo htmlspecialchars($result['request_status']); ?></strong>.</div>
        <a href="track.php?reference_no=<?php echo urlencode($reference_no); ?>&student_number=<?php echo urlencode($student_number); ?>" class="btn btn-secondary">Back to Your Request</a>
    <?php else: ?>
        <h1>Edit Your Request</h1>
        <p class="subtitle">You can edit your details while your request is still Pending.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="requestForm" method="POST" action="edit_request.php" novalidate>
            <input type="hidden" name="reference_no" value="<?php echo htmlspecialchars($reference_no); ?>">
            <input type="hidden" name="student_number" value="<?php echo htmlspecialchars($student_number); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required
                        value="<?php echo htmlspecialchars($result['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="course">Course / Program *</label>
                    <input type="text" id="course" name="course" required
                        value="<?php echo htmlspecialchars($result['course']); ?>">
                </div>

                <div class="form-group">
                    <label for="document_type_trigger">Document Needed *</label>
                    <div class="custom-select-wrapper" id="document_type_wrapper">
                        <button type="button" class="custom-select-trigger" id="document_type_trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="custom-select-value"><?php echo htmlspecialchars($result['document_type'] ?: 'Select Document'); ?></span>
                            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
                                <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="custom-select-list" role="listbox" hidden>
                            <li class="custom-select-option<?php echo $result['document_type'] === '' ? ' is-selected' : ''; ?>" role="option" data-value="">Select Document</li>
                            <li class="custom-select-option<?php echo $result['document_type'] === 'Certificate of Registration' ? ' is-selected' : ''; ?>" role="option" data-value="Certificate of Registration">Certificate of Registration</li>
                            <li class="custom-select-option<?php echo $result['document_type'] === 'Certificate of Grades' ? ' is-selected' : ''; ?>" role="option" data-value="Certificate of Grades">Certificate of Grades</li>
                            <li class="custom-select-option<?php echo $result['document_type'] === 'Diploma (Copy / Authentication)' ? ' is-selected' : ''; ?>" role="option" data-value="Diploma (Copy / Authentication)">Diploma (Copy / Authentication)</li>
                        </ul>
                        <select id="document_type" name="document_type" class="native-select-hidden" tabindex="-1" aria-hidden="true">
                            <option value="" <?php echo optionSelected('', $result['document_type']); ?>>Select Document</option>
                            <option<?php echo optionSelected('Certificate of Registration', $result['document_type']); ?>>Certificate of Registration</option>
                                <option<?php echo optionSelected('Certificate of Grades', $result['document_type']); ?>>Certificate of Grades</option>
                                    <option<?php echo optionSelected('Diploma (Copy / Authentication)', $result['document_type']); ?>>Diploma (Copy / Authentication)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="year_level_group" <?php echo in_array($result['document_type'], $academicDocs, true) ? '' : 'hidden'; ?>>
                    <label for="year_level_trigger">Year Level *</label>
                    <div class="custom-select-wrapper" id="year_level_wrapper">
                        <button type="button" class="custom-select-trigger" id="year_level_trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="custom-select-value"><?php echo htmlspecialchars($result['year_level'] ?: 'Select Year Level'); ?></span>
                            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
                                <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="custom-select-list" role="listbox" hidden>
                            <li class="custom-select-option<?php echo $result['year_level'] === '' ? ' is-selected' : ''; ?>" role="option" data-value="">Select Year Level</li>
                            <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate / Alumni'] as $yl): ?>
                                <li class="custom-select-option<?php echo $result['year_level'] === $yl ? ' is-selected' : ''; ?>" role="option" data-value="<?php echo htmlspecialchars($yl); ?>"><?php echo htmlspecialchars($yl); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <select id="year_level" name="year_level" class="native-select-hidden" tabindex="-1" aria-hidden="true">
                            <option value="" <?php echo optionSelected('', $result['year_level']); ?>>Select Year Level</option>
                            <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate / Alumni'] as $yl): ?>
                                <option<?php echo optionSelected($yl, $result['year_level']); ?>><?php echo htmlspecialchars($yl); ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="semester_group" <?php echo in_array($result['document_type'], $academicDocs, true) ? '' : 'hidden'; ?>>
                    <label for="semester_trigger">Semester *</label>
                    <div class="custom-select-wrapper" id="semester_wrapper">
                        <button type="button" class="custom-select-trigger" id="semester_trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="custom-select-value"><?php echo htmlspecialchars($result['semester'] ?: 'Select Semester'); ?></span>
                            <svg class="custom-select-caret" viewBox="0 0 20 20" fill="none">
                                <path d="M5.5 8L10 12.5L14.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="custom-select-list" role="listbox" hidden>
                            <li class="custom-select-option<?php echo $result['semester'] === '' ? ' is-selected' : ''; ?>" role="option" data-value="">Select Semester</li>
                            <?php foreach (['1st Semester', '2nd Semester', 'Summer'] as $sem): ?>
                                <li class="custom-select-option<?php echo $result['semester'] === $sem ? ' is-selected' : ''; ?>" role="option" data-value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <select id="semester" name="semester" class="native-select-hidden" tabindex="-1" aria-hidden="true">
                            <option value="" <?php echo optionSelected('', $result['semester']); ?>>Select Semester</option>
                            <?php foreach (['1st Semester', '2nd Semester', 'Summer'] as $sem): ?>
                                <option<?php echo optionSelected($sem, $result['semester']); ?>><?php echo htmlspecialchars($sem); ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="purpose">Purpose / Reason for Request *</label>
                <textarea id="purpose" name="purpose" rows="4" required><?php echo htmlspecialchars($result['purpose']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="track.php?reference_no=<?php echo urlencode($reference_no); ?>&student_number=<?php echo urlencode($student_number); ?>" class="btn btn-secondary" style="margin-top:12px;">Cancel</a>
        </form>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>