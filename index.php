<?php
$pageTitle = 'Home';
$basePath = '';
include 'includes/header.php';
?>

<section class="hero">
  <div class="hero-copy">
    <h1>Request your documents without the long line</h1>
    <p>Submit a request for your COR with registrar's signature, COG, Prospectus, or other
      registrar document online.</p>
    <div class="hero-actions">
      <a href="request.php" class="btn btn-primary">Submit a Request</a>
      <a href="track.php" class="btn btn-secondary">Track My Request</a>
    </div>
  </div>
</section>

<section class="how-it-works">
  <h2 class="reveal">How it works</h2>
  <div class="steps">
    <div class="step-card reveal">
      <span class="step-number">1</span>
      <h3>Fill up the form</h3>
      <p>Enter your details accurately. Free and instant request. No account needed.</p>
    </div>
    <div class="step-card reveal">
      <span class="step-number">2</span>
      <h3>Save your receipt</h3>
      <p>Print your claim receipt — it shows your claim date and what is needed to track your request.</p>
    </div>
    <div class="step-card reveal">
      <span class="step-number">3</span>
      <h3>Pay at the cashier</h3>
      <p>Pay the processing fee at the Cashier's Office and keep the official receipt securely.</p>
    </div>
    <div class="step-card reveal">
      <span class="step-number">4</span>
      <h3>Registrar processes it</h3>
      <p>The registrar's office carefully reviews and prepares your document for release.</p>
    </div>
    <div class="step-card reveal">
      <span class="step-number">5</span>
      <h3>Claim your document</h3>
      <p>Once marked 'Ready for Pickup', bring your receipt and a valid ID to claim in registrar office.</p>
    </div>
  </div>
</section>

<section class="documents-offered reveal">
  <h2>Documents you can request</h2>

  <div class="doc-marquee">
    <div class="doc-track">
      <ul class="doc-list doc-set">
        <li>Certificate of Registration</li>
        <li>Certificate of Grades</li>
        <li>Diploma</li>
        <li aria-hidden="true">Certificate of Registration</li>
        <li aria-hidden="true">Certificate of Grades</li>
        <li aria-hidden="true">Diploma</li>
        <li aria-hidden="true">Certificate of Registration</li>
        <li aria-hidden="true">Certificate of Grades</li>
        <li aria-hidden="true">Diploma</li>
      </ul>

      <ul class="doc-list doc-set" aria-hidden="true">
        <li>Certificate of Registration</li>
        <li>Certificate of Grades</li>
        <li>Diploma</li>
        <li>Certificate of Registration</li>
        <li>Certificate of Grades</li>
        <li>Diploma</li>
        <li>Certificate of Registration</li>
        <li>Certificate of Grades</li>
        <li>Diploma</li>
      </ul>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>