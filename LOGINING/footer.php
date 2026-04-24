<style>
.site-footer {
  background: #060e1f;
  border-top: 1px solid rgba(255,255,255,0.06);
  padding: 28px 24px 20px;
  position: relative;
  z-index: 10;
}

.footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.footer-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.footer-brand-icon {
  width: 28px;
  height: 28px;
  background: linear-gradient(135deg, #1e6fff, #0f4fd6);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}

.footer-brand-text {
  font-family: 'Sora', 'Segoe UI', sans-serif;
  font-size: 12px;
  font-weight: 700;
  color: rgba(255,255,255,0.75);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.footer-links {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: center;
}

.footer-links a {
  color: rgba(255,255,255,0.38);
  text-decoration: none;
  font-size: 11px;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 6px;
  transition: color 0.2s, background 0.2s;
  letter-spacing: 0.02em;
}

.footer-links a:hover {
  color: rgba(255,255,255,0.75);
  background: rgba(255,255,255,0.05);
}

.footer-links .dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  flex-shrink: 0;
}

.footer-copy {
  font-size: 10.5px;
  color: rgba(255,255,255,0.25);
  letter-spacing: 0.03em;
  text-align: center;
  line-height: 1.6;
}

.footer-copy span {
  color: #f0b429;
  opacity: 0.7;
}

.footer-divider {
  width: 100%;
  max-width: 400px;
  height: 1px;
  background: rgba(255,255,255,0.05);
}
</style>

<footer class="site-footer" role="contentinfo">
  <div class="footer-inner">
    <p class="footer-copy">
      &copy; <?= date('Y') ?> College of Computer Studies &mdash; University of Cebu &nbsp;<span>&hearts;</span><br>
      All rights reserved. For authorized students only.
    </p>

  </div>
</footer>