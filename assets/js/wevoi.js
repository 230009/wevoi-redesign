(function () {
  'use strict';
  function init(root) {
    root.classList.add('wv-js');
    var $ = function (s, c) { return (c || root).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };

    // Mobile menu
    var toggle = $('.wv-menu-toggle'), nav = $('.wv-navlinks');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('wv-open');
        root.classList.toggle('wv-menu-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Dropdowns (touch / click)
    $$('.wv-navitem').forEach(function (item) {
      var btn = $('.wv-dropbtn', item), menu = $('.wv-dropdown', item);
      if (!btn || !menu) return;
      item.classList.add('wv-has-dropdown');
      btn.addEventListener('click', function (e) {
        var touch = window.matchMedia('(hover: none)').matches || window.innerWidth <= 1020;
        if (!touch) return;
        e.preventDefault();
        $$('.wv-navitem.wv-open').forEach(function (x) { if (x !== item) x.classList.remove('wv-open'); });
        item.classList.toggle('wv-open');
      });
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest || !e.target.closest('.wv-navitem')) {
        $$('.wv-navitem.wv-open').forEach(function (x) { x.classList.remove('wv-open'); });
      }
    });

    // Scroll reveal
    var reveals = $$('.wv-reveal');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('wv-visible'); io.unobserve(en.target); }
        });
      }, { threshold: 0.12 });
      reveals.forEach(function (el) { io.observe(el); });
    } else {
      reveals.forEach(function (el) { el.classList.add('wv-visible'); });
    }

    // Age category tabs (home)
    var tabs = $$('.wv-age-tab'), panels = $$('[data-age-content]');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-age');
        tabs.forEach(function (t) { t.classList.remove('wv-active'); });
        panels.forEach(function (p) { p.classList.remove('wv-active'); });
        tab.classList.add('wv-active');
        var p = $('[data-age-content="' + id + '"]');
        if (p) p.classList.add('wv-active');
      });
    });

    // Workshop month tabs
    var mtabs = $$('.wv-month-tab'), mpanels = $$('.wv-month-panel');
    mtabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = 'wv-' + tab.getAttribute('data-month');
        mtabs.forEach(function (t) { t.classList.remove('wv-active'); });
        mpanels.forEach(function (p) { p.classList.toggle('wv-active', p.id === id); });
        tab.classList.add('wv-active');
      });
    });

    // Signup form role-specific fields and database-backed school choices.
    var signup = $('.wv-signup-fields');
    if (signup) {
      var roleInputs = $$('input[name="role"]', signup);
      var studentFields = $('#wv-signup-student-fields', signup);
      var birthDateInput = $('#wv-signup-birth-date', signup);
      var genderSelect = $('#wv-signup-gender', signup);
      var schoolFields = $('#wv-signup-school-fields', signup);
      var schoolSelect = $('#wv-signup-school', signup);
      var newSchoolField = $('#wv-signup-new-school-field', signup);
      var newSchoolInput = $('#wv-signup-new-school', signup);
      var agencyFields = $('#wv-signup-agency-fields', signup);
      var companyInput = $('#wv-signup-company', signup);
      var countrySelect = $('#wv-signup-country', signup);
      function updateSignupRole() {
        var selectedRole = $('input[name="role"]:checked', signup);
        var role = selectedRole ? selectedRole.value : '';
        var isStudent = role === 'student';
        var needsSchool = ['student', 'supervisor', 'judge'].indexOf(role) !== -1;
        var isAgency = role === 'agency';

        studentFields.hidden = !isStudent;
        studentFields.disabled = !isStudent;
        birthDateInput.required = isStudent;
        genderSelect.required = isStudent;
        schoolFields.hidden = !needsSchool;
        schoolFields.setAttribute('aria-hidden', needsSchool ? 'false' : 'true');
        schoolSelect.disabled = !needsSchool || !countrySelect.value;
        schoolSelect.required = needsSchool;
        agencyFields.hidden = !isAgency;
        companyInput.disabled = !isAgency;
        companyInput.required = isAgency;
        updateSignupSchool();
      }

      function updateSignupSchool() {
        var selectedRole = $('input[name="role"]:checked', signup);
        var needsSchool = selectedRole && ['student', 'supervisor', 'judge'].indexOf(selectedRole.value) !== -1;
        var countryId = countrySelect.value;
        Array.prototype.forEach.call(schoolSelect.options, function (option) {
          if (!option.value) return;
          var visible = option.value === 'other' || option.getAttribute('data-country') === countryId;
          option.hidden = !visible;
          if (!visible && option.selected) schoolSelect.value = '';
        });
        schoolSelect.disabled = !needsSchool || !countrySelect.value;
        schoolSelect.required = !!needsSchool;
        var needsNewSchool = schoolSelect.value === 'other' && !!needsSchool;
        newSchoolField.hidden = !needsNewSchool;
        newSchoolInput.disabled = newSchoolField.hidden;
        newSchoolInput.required = !newSchoolField.hidden;
      }

      roleInputs.forEach(function (input) {
        input.addEventListener('change', updateSignupRole);
      });
      countrySelect.addEventListener('change', updateSignupSchool);
      schoolSelect.addEventListener('change', updateSignupSchool);
      updateSignupRole();
    }

    // Login page visual prototype password visibility control
    var loginPassword = $('#wv-login-password');
    var passwordToggle = $('.wv-login-password-toggle');
    if (loginPassword && passwordToggle) {
      passwordToggle.addEventListener('click', function () {
        var reveal = loginPassword.type === 'password';
        loginPassword.type = reveal ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        passwordToggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      });
    }

    // Countdown
    $$('[data-countdown]').forEach(function (box) {
      var target = new Date(box.getAttribute('data-countdown')).getTime();
      var cells = $$('[data-time]', box);
      function pad(n) { return n < 10 ? '0' + n : String(n); }
      function tick() {
        var d = Math.max(0, target - Date.now()), s = Math.floor(d / 1000);
        var v = [Math.floor(s / 86400), Math.floor(s % 86400 / 3600), Math.floor(s % 3600 / 60), s % 60];
        cells.forEach(function (c, i) { c.textContent = pad(v[i] || 0); });
      }
      tick(); setInterval(tick, 1000);
    });
  }
  function boot() { Array.prototype.forEach.call(document.querySelectorAll('.wevoi-app'), init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();

/* Interactive map loader: loads Leaflet only when a map is on the page */
(function () {
  function start() {
    if (!document.querySelector('[data-wv-map]')) return;
    var s = document.querySelector('script[src*="assets/js/wevoi.js"]');
    if (!s) return;
    var base = s.src.split('assets/js/')[0];
    window.WeVoiMapBase = base;
    function css(h) { var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = h; document.head.appendChild(l); }
    function js(h, cb) { var e = document.createElement('script'); e.src = h; e.onload = cb; document.body.appendChild(e); }
    css(base + 'assets/vendor/leaflet/leaflet.css');
    css(base + 'assets/css/wevoi-map.css');
    js(base + 'assets/vendor/leaflet/leaflet.js', function () { js(base + 'assets/js/wevoi-map.js'); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
