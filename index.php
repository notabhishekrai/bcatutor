<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Home';
$fullBleed = true;
$metaDescription = 'Notes, question papers, and solution papers for BCA students, organized by semester and subject. Plus a Nepali tech relevant blog.';
require __DIR__ . '/includes/header.php';
?>

<style>
.landing {
    --paper: #F0F1E9;
    --ink: #1E2A22;
    --pine: #2F5D4E;
    --pine-dark: #234438;
    --mustard: #E3A73C;
    --line: #D3D6C6;
    --muted: #5B6459;

    position: relative;
    width: 100%;
    
    padding: 56px 20px 72px 20px;
    background: var(--paper);
    color: var(--ink);
    overflow: hidden;
}

.landing-inner {
    position: relative;
    max-width: 960px;
    margin: 0 auto;
    padding-left: 44px;
}

/* Signature: ring-binder holes, anchored to the text column */
.landing-inner::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 6px;
    width: 14px;
    background-image: radial-gradient(circle, #ffffff 0 5px, transparent 5.5px);
    background-size: 14px 64px;
    background-repeat: repeat-y;
    background-position: center 4px;
    box-shadow: inset 0 0 0 1px var(--line);
    opacity: 0.9;
}

.landing-inner::after {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 6px;
    width: 14px;
    background-image: radial-gradient(circle, transparent 0 4.5px, var(--line) 5px 5.5px, transparent 6px);
    background-size: 14px 64px;
    background-repeat: repeat-y;
    background-position: center 4px;
}

.eyebrow {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pine);
    display: inline-block;
    padding: 4px 10px;
    border: 1px solid var(--pine);
    border-radius: 3px;
    margin-bottom: 22px;
}

.landing h1 {
    font-family: 'Fraunces', serif;
    font-optical-sizing: auto;
    font-weight: 600;
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    line-height: 1.08;
    margin: 0 0 20px 0;
    letter-spacing: -0.01em;
}

.landing h1 em {
    font-style: italic;
    font-weight: 500;
    color: var(--pine);
}

.landing-sub {
    font-family: 'Work Sans', sans-serif;
    font-size: 1.08rem;
    color: var(--muted);
    max-width: 46ch;
    margin: 0 0 40px 0;
    line-height: 1.65;
}

/* Two entry points, styled as folder tabs */
.tab-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 64px;
}

.tab-card {
    position: relative;
    display: block;
    text-decoration: none;
    background: #FBFBF6;
    border: 1px solid var(--line);
    border-radius: 4px 4px 10px 10px;
    padding: 30px 24px 26px;
    color: var(--ink);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.tab-card::before {
    content: attr(data-tab);
    position: absolute;
    top: -17px;
    left: 24px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #fff;
    padding: 5px 12px;
    border-radius: 4px 4px 0 0;
}

.tab-card--notes::before { background: var(--pine); }
.tab-card--blog::before { background: var(--mustard); }
.tab-card--quiz::before { background: #9C3D8B; }

.tab-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -12px rgba(30, 42, 34, 0.35);
}

.tab-card:focus-visible {
    outline: 2px solid var(--pine);
    outline-offset: 3px;
}

.tab-card h2 {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: 1.5rem;
    margin: 6px 0 10px;
}

.tab-card p {
    font-family: 'Work Sans', sans-serif;
    color: var(--muted);
    font-size: 0.95rem;
    line-height: 1.55;
    margin: 0 0 18px;
}

.tab-card .go {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.82rem;
    font-weight: 500;
}

.tab-card--notes .go { color: var(--pine); }
.tab-card--blog .go { color: #A8792A; }
.tab-card--quiz .go { color: #9C3D8B; }

/* How it's organized — a real sequence, so numbers are earned here */
.flow {
    border-top: 1px solid var(--line);
    padding-top: 40px;
}

.flow-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 24px;
}

.flow-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    align-items: flex-start;
}

.flow-step {
    flex: 1;
    min-width: 180px;
    padding-right: 18px;
}

.flow-step .num {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.85rem;
    color: #fff;
    background: var(--pine);
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.flow-step h3 {
    font-family: 'Work Sans', sans-serif;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 6px;
}

.flow-step p {
    font-family: 'Work Sans', sans-serif;
    font-size: 0.88rem;
    color: var(--muted);
    margin: 0;
    line-height: 1.5;
}

.flow-arrow {
    font-family: 'IBM Plex Mono', monospace;
    color: var(--line);
    font-size: 1.2rem;
    padding: 0 6px;
    margin-top: 5px;
    display: none;
}

@media (min-width: 640px) {
    .flow-arrow { display: block; }
}

@media (max-width: 640px) {
    .tab-row { grid-template-columns: 1fr; }
    .landing { padding: 40px 16px 56px; }
    .landing-inner { padding-left: 30px; }
    .landing-inner::before, .landing-inner::after { left: 4px; }
}

@media (prefers-reduced-motion: reduce) {
    .tab-card { transition: none; }
}

.btn{
    padding: 14px 24px;
    background: #000;
    color: #fff !important;
}
</style>

<div class="landing">
    <div class="landing-inner">
        <span class="eyebrow">BCA · Semester 1–8</span>

        <h1>One binder for<br><em>every semester.</em></h1>

        <p class="landing-sub">
            Class notes and solved question papers, organized the way you'd actually
            look for them — plus a blog for everything outside the syllabus.
        </p>

        <div class="tab-row">
            <a href="quizzes" class="tab-card tab-card--quiz" data-tab="Practice">
                <h2>BCA Entrance</h2>
                <p>Practice quizzes to help you prepare for the BCA entrance exam.</p>
                <span class="go btn">Take a Quiz &rarr;</span>
            </a>
            <a href="semesters" class="tab-card tab-card--notes" data-tab="Coursework">
                <h2>Notes & Solutions</h2>
                <p>Browse by semester, then subject. Class notes and solved papers, side by side.</p>
                <span class="go btn">Browse Notes & Solutions &rarr;</span>
            </a>
            <a href="blog" class="tab-card tab-card--blog" data-tab="Writing">
                <h2>Blog</h2>
                <p>Study tips, exam strategy, and things worth writing about outside the syllabus.</p>
                <span class="go btn">Read the blog &rarr;</span>
            </a>
        </div>

        <div class="flow">
            <p class="flow-label">How Notes & Solutions is organized</p>
            <div class="flow-steps">
                <div class="flow-step">
                    <span class="num">1</span>
                    <h3>Semester</h3>
                    <p>Pick your semester, 1 through 8.</p>
                </div>
                <span class="flow-arrow">&rarr;</span>
                <div class="flow-step">
                    <span class="num">2</span>
                    <h3>Subject</h3>
                    <p>Pick a subject from that semester's syllabus.</p>
                </div>
                <span class="flow-arrow">&rarr;</span>
                <div class="flow-step">
                    <span class="num">3</span>
                    <h3>Notes & Solutions</h3>
                    <p>Find class notes and solved papers together.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
