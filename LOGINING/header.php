<?php
// header.php — Upgraded SIT-IN Monitoring System Header
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
  --nav-h: 64px;
  --navy: #0a1628;
  --navy-mid: #0f2044;
  --blue: #1e6fff;
  --blue-bright: #4d8fff;
  --gold: #f0b429;
  --text-nav: rgba(255,255,255,0.82);
  --text-nav-hover: #fff;
  --glass-bg: rgba(255,255,255,0.04);
  --glass-border: rgba(255,255,255,0.09);
  font-family: 'DM Sans', sans-serif;
}

/* ── Reset topnav from design.css ── */
.topnav {
  all: unset;
  display: block;
  position: sticky;
  top: 0;
  z-index: 1000;
  height: var(--nav-h);
  background: linear-gradient(90deg, #0a1628 0%, #0d1e3a 60%, #0f2348 100%);
  border-bottom: 1px solid rgba(255,255,255,0.07);
  box-shadow: 0 2px 32px rgba(0,0,0,0.35);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

.topnav::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 0% 50%, rgba(30,111,255,0.12) 0%, transparent 55%),
    radial-gradient(ellipse at 100% 50%, rgba(240,180,41,0.06) 0%, transparent 50%);
  pointer-events: none;
}

.nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  height: var(--nav-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  position: relative;
  z-index: 1;
}

/* ── Brand ── */
.nav-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  flex-shrink: 0;
}

.nav-brand-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #1e6fff, #4d8fff);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(30,111,255,0.45);
  font-size: 1rem;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.nav-brand-icon::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
}

.nav-brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}

.nav-brand-title {
  font-family: 'Sora', sans-serif;
  font-size: 0.8rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.nav-brand-sub {
  font-size: 0.65rem;
  color: var(--gold);
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.9;
}

/* ── Nav links ── */
.nav-links {
  display: flex;
  align-items: center;
  gap: 4px;
}

.nav-link {
  color: var(--text-nav);
  text-decoration: none;
  font-size: 0.825rem;
  font-weight: 500;
  padding: 8px 14px;
  border-radius: 8px;
  transition: color 0.2s, background 0.2s;
  position: relative;
  white-space: nowrap;
  letter-spacing: 0.01em;
}

.nav-link:hover {
  color: var(--text-nav-hover);
  background: var(--glass-bg);
}

/* ── Dropdown ── */
.nav-dropdown {
  position: relative;
}

.nav-dropdown > .nav-link {
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: default;
}

.nav-dropdown > .nav-link .chevron {
  display: inline-block;
  width: 12px;
  height: 12px;
  transition: transform 0.25s;
  opacity: 0.7;
}

.nav-dropdown:hover > .nav-link .chevron {
  transform: rotate(180deg);
}

.dropdown-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%) translateY(-4px);
  background: #0f1f3d;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 6px;
  min-width: 160px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(30,111,255,0.12);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s, transform 0.2s;
  z-index: 100;
}

.nav-dropdown:hover .dropdown-panel {
  opacity: 1;
  pointer-events: auto;
  transform: translateX(-50%) translateY(0);
}

.dropdown-panel a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  color: rgba(255,255,255,0.75);
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 500;
  border-radius: 8px;
  transition: color 0.18s, background 0.18s;
  white-space: nowrap;
}

.dropdown-panel a:hover {
  color: #fff;
  background: rgba(30,111,255,0.18);
}

.dropdown-panel a .dp-icon {
  font-size: 0.9rem;
  opacity: 0.8;
}

/* ── Auth buttons ── */
.nav-auth {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-nav-ghost {
  color: var(--text-nav);
  text-decoration: none;
  font-size: 0.825rem;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.14);
  transition: color 0.2s, background 0.2s, border-color 0.2s;
  letter-spacing: 0.01em;
}

.btn-nav-ghost:hover {
  color: #fff;
  background: rgba(255,255,255,0.07);
  border-color: rgba(255,255,255,0.25);
}

.btn-nav-solid {
  color: #fff;
  text-decoration: none;
  font-size: 0.825rem;
  font-weight: 700;
  padding: 8px 20px;
  border-radius: 8px;
  background: linear-gradient(135deg, #1e6fff, #0f4fd6);
  box-shadow: 0 4px 16px rgba(30,111,255,0.4);
  transition: box-shadow 0.2s, transform 0.18s, background 0.2s;
  letter-spacing: 0.015em;
  position: relative;
  overflow: hidden;
}

.btn-nav-solid::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
}

.btn-nav-solid:hover {
  background: linear-gradient(135deg, #3d7fff, #1e5fe6);
  box-shadow: 0 6px 24px rgba(30,111,255,0.55);
  transform: translateY(-1px);
}

/* ── Mobile toggle ── */
.nav-toggle {
  display: none;
  background: none;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
  gap: 5px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.nav-toggle span {
  display: block;
  width: 18px;
  height: 2px;
  background: rgba(255,255,255,0.8);
  border-radius: 2px;
  transition: transform 0.2s, opacity 0.2s;
}

/* ── Divider ── */
.nav-divider {
  width: 1px;
  height: 20px;
  background: rgba(255,255,255,0.1);
  margin: 0 4px;
}

/* ── Active state ── */
.nav-link.active {
  color: #fff;
  background: rgba(30,111,255,0.15);
}

@media (max-width: 768px) {
  .nav-toggle { display: flex; }
  .nav-center, .nav-auth { display: none; }
  .nav-brand-sub { display: none; }
}
</style>

<nav class="topnav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">

    <!-- Brand -->
    <a href="login.php" class="nav-brand" aria-label="CCS Sit-in Home">
      <div class="nav-brand-icon">🖥</div>
      <div class="nav-brand-text">
        <span class="nav-brand-title">CCS Sit-In</span>
        <span class="nav-brand-sub">Monitoring System</span>
      </div>
    </a>

    <!-- Center links -->
    <div class="nav-links nav-center">
      <a href="login.php" class="nav-link">Home</a>

      <div class="nav-dropdown">
        <a href="#" class="nav-link" onclick="return false;">
          Community
          <svg class="chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="2,4 6,8 10,4"/>
          </svg>
        </a>
        <div class="dropdown-panel">
          <a href="#"><span class="dp-icon">💬</span> Forum</a>
          <a href="#"><span class="dp-icon">📁</span> Resources</a>
        </div>
      </div>

      <a href="#" class="nav-link">About</a>
    </div>

    <!-- Auth buttons -->
    <div class="nav-auth">
      <a href="login.php" class="btn-nav-ghost">Log In</a>
      <a href="register.php" class="btn-nav-solid">Register</a>
    </div>

    <!-- Mobile toggle -->
    <button class="nav-toggle" aria-label="Toggle menu" onclick="this.parentElement.parentElement.classList.toggle('nav-open')">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>