/* ==========================================================================
   Money Wise 2026 — Main JavaScript
   Minimal, vanilla JS for menu toggle, smooth scroll, lazy loading
   ========================================================================== */

(function() {
  'use strict';

  // ---------- Mobile Menu Toggle ----------
  const navToggle = document.querySelector('.nav-toggle');
  const navMenu = document.querySelector('.nav-menu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function() {
      const isOpen = navMenu.classList.toggle('active');
      navToggle.setAttribute('aria-expanded', isOpen);
      navToggle.innerHTML = isOpen ? '&times;' : '&#9776;';
    });

    // Close menu when clicking a link (mobile)
    navMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          navMenu.classList.remove('active');
          navToggle.setAttribute('aria-expanded', 'false');
          navToggle.innerHTML = '&#9776;';
        }
      });
    });
  }

  // ---------- Smooth Scroll for Anchor Links ----------
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const targetId = this.getAttribute('href');
      if (targetId === '#' || targetId === '#!') return;
      const target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        const headerOffset = 80;
        const elementPosition = target.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  // ---------- Lazy Loading (Native) ----------
  // Modern browsers handle loading="lazy" natively; this is fallback for older
  if ('loading' in HTMLImageElement.prototype === false) {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
              img.src = img.dataset.src;
            }
            obs.unobserve(img);
          }
        });
      });
      lazyImages.forEach(img => observer.observe(img));
    }
  }

  // ---------- Newsletter Form (Placeholder) ----------
  const newsletterForms = document.querySelectorAll('.newsletter-form');
  newsletterForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const email = form.querySelector('input[type="email"]').value;
      if (email && email.includes('@')) {
        alert('Thank you for subscribing! We\'ll be in touch soon.');
        form.reset();
      }
    });
  });

  // ---------- Contact Form (Placeholder) ----------
  const contactForm = document.querySelector('#contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      e.preventDefault();
      alert('Thank you for your message! We will respond within 2 business days.');
      contactForm.reset();
    });
  }

  // ---------- Current Year in Footer ----------
  const yearEl = document.querySelector('.current-year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

})();
