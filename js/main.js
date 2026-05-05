/* =====================================================
   DIOS Hair | Makeup — Main JavaScript
   Handles: sticky nav, mobile menu, form validation,
            date restrictions, form submission
   ===================================================== */

(function () {
  'use strict';

  /* ---- STICKY HEADER ---- */
  const header = document.getElementById('header');
  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 60);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---- MOBILE NAV ---- */
  const navToggle  = document.getElementById('navToggle');
  const navLinks   = document.getElementById('navLinks');
  const navBackdrop = document.getElementById('navBackdrop');

  function lockBodyScroll() {
    const scrollbarComp = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.setProperty('--scrollbar-comp', scrollbarComp > 0 ? scrollbarComp + 'px' : '0px');
    document.body.classList.add('nav-open');
  }

  function unlockBodyScroll() {
    document.body.classList.remove('nav-open');
    document.body.style.removeProperty('--scrollbar-comp');
  }

  function openNav() {
    navLinks.classList.add('open');
    navToggle.classList.add('open');
    navToggle.setAttribute('aria-expanded', 'true');
    lockBodyScroll();
    if (navBackdrop) navBackdrop.classList.add('open');
  }

  function closeNav() {
    navLinks.classList.remove('open');
    navToggle.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
    unlockBodyScroll();
    if (navBackdrop) navBackdrop.classList.remove('open');
  }

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      navLinks.classList.contains('open') ? closeNav() : openNav();
    });
  }

  if (navBackdrop) {
    navBackdrop.addEventListener('click', closeNav);
  }

  // Close menu when a nav link is clicked
  if (navLinks) {
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeNav);
    });
  }

  // Close on outside click
  document.addEventListener('click', function (e) {
    if (!navLinks || !navToggle || !navBackdrop) {
      return;
    }
    if (navLinks.classList.contains('open') &&
        !navLinks.contains(e.target) &&
        !navToggle.contains(e.target) &&
        !navBackdrop.contains(e.target)) {
      closeNav();
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 768 && navLinks && navLinks.classList.contains('open')) {
      closeNav();
    }
  });

  /* ---- DATE: restrict to today onwards ---- */
  const dateInput = document.getElementById('preferredDate');
  if (dateInput) {
    const today = new Date();
    // Minimum = tomorrow (can't book same day online)
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];

    // Max = 90 days out
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 90);
    dateInput.max = maxDate.toISOString().split('T')[0];
  }

  /* ---- DYNAMIC SERVICE FIELDS ---- */
  var serviceSelectEl   = document.getElementById('service');
  var subTypeRow        = document.getElementById('subTypeRow');
  var subTypeSelectEl   = document.getElementById('subType');
  var subTypeLabelEl    = document.getElementById('subTypeLabel');
  var lengthGroup       = document.getElementById('lengthGroup');
  var timeSelectEl      = document.getElementById('preferredTime');
  var svcInfoBanner     = document.getElementById('serviceInfoBanner');
  var svcInfoText       = document.getElementById('serviceInfoText');

  var SERVICE_CONFIG = {
    'braids': {
      label: 'Type of Braids',
      options: ['Knotless Braids', 'Box Braids', 'Feed-in Braids', 'Goddess Braids', 'Faux Locs'],
      showLength: true,
      info: '<strong>Duration:</strong> 3–4 hours &nbsp;·&nbsp; <strong>Team:</strong> 1 client / 2 Braiders &nbsp;·&nbsp; <strong>Slots:</strong> 7:30am · 11:30am · 2:30pm',
      slots: [
        { value: '07:30', label: '7:30 AM' },
        { value: '11:30', label: '11:30 AM' },
        { value: '14:30', label: '2:30 PM' }
      ]
    },
    'cornrows': {
      label: 'Cornrow Style',
      options: ['Classic Cornrows', 'Feed-in Cornrows', 'Curved / Pattern Cornrows', 'Braided Updo Cornrows'],
      showLength: false,
      info: '<strong>Duration:</strong> 2–3 hours &nbsp;·&nbsp; <strong>Team:</strong> 2 Stylists',
      slots: null
    },
    'ponytail': {
      label: 'Ponytail Style',
      options: ['Straight Ponytail', 'Curly Ponytail', 'Afro Kinky Ponytail'],
      showLength: false,
      info: null,
      slots: null
    },
    'wig-installation': {
      label: 'Wig Type',
      options: ['Full Lace Wig', 'Frontal Wig (13×4)', '360 Lace Wig', 'Closure Wig (4×4)', 'Closure Wig (5×5)'],
      showLength: false,
      info: 'Pricing varies by wig type — will be confirmed on booking',
      slots: null
    },
    'makeup': {
      label: 'Makeup Type',
      options: ['Bridal Makeup', 'Events & Functions', 'Editorial Makeup', 'Everyday Glam', 'Graduation Makeup'],
      showLength: false,
      info: 'Makeup enquiries are handled via WhatsApp — 073 266 8348',
      slots: null
    }
  };

  var DEFAULT_SLOTS = [
    { value: '', label: 'Select time…' },
    { value: '08:00', label: '08:00 AM' },
    { value: '09:00', label: '09:00 AM' },
    { value: '10:00', label: '10:00 AM' },
    { value: '11:00', label: '11:00 AM' },
    { value: '12:00', label: '12:00 PM' },
    { value: '13:00', label: '01:00 PM' },
    { value: '14:00', label: '02:00 PM' },
    { value: '15:00', label: '03:00 PM' },
    { value: '16:00', label: '04:00 PM' },
    { value: '17:00', label: '05:00 PM' },
    { value: 'before-hours', label: 'Before Hours (extra R200)' },
    { value: 'after-hours', label: 'After Hours (extra R200)' }
  ];

  function setTimeSlots(slots) {
    if (!timeSelectEl) return;
    var list = slots
      ? [{ value: '', label: 'Select time…' }].concat(slots)
      : DEFAULT_SLOTS;
    timeSelectEl.innerHTML = '';
    list.forEach(function (s) {
      var opt = document.createElement('option');
      opt.value = s.value;
      opt.textContent = s.label;
      timeSelectEl.appendChild(opt);
    });
  }

  function updateServiceFields(val) {
    var config = SERVICE_CONFIG[val];
    if (!config) {
      if (subTypeRow) subTypeRow.hidden = true;
      if (svcInfoBanner) svcInfoBanner.hidden = true;
      setTimeSlots(null);
      return;
    }
    // Info banner
    if (config.info) {
      if (svcInfoText) svcInfoText.innerHTML = config.info;
      if (svcInfoBanner) svcInfoBanner.hidden = false;
    } else {
      if (svcInfoBanner) svcInfoBanner.hidden = true;
    }
    // Sub-type label & options
    if (subTypeLabelEl) subTypeLabelEl.textContent = config.label + ' *';
    if (subTypeSelectEl) {
      subTypeSelectEl.innerHTML = '<option value="">Select…</option>';
      config.options.forEach(function (optText) {
        var el = document.createElement('option');
        el.value = optText.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        el.textContent = optText;
        subTypeSelectEl.appendChild(el);
      });
    }
    if (subTypeRow) subTypeRow.hidden = false;
    if (lengthGroup) lengthGroup.hidden = !config.showLength;
    // Time slots
    setTimeSlots(config.slots || null);
  }

  if (serviceSelectEl) {
    serviceSelectEl.addEventListener('change', function () {
      updateServiceFields(this.value);
      clearError('subType');
      clearError('hairLength');
    });
  }

  /* ---- FORM VALIDATION & SUBMISSION ---- */
  const form       = document.getElementById('bookingForm');
  const submitBtn  = document.getElementById('submitBtn');
  const formSuccess = document.getElementById('formSuccess');

  if (form) {

  function showError(fieldId, message) {
    const input = document.getElementById(fieldId);
    const error = document.getElementById(fieldId + 'Error');
    if (input) input.classList.add('error');
    if (error) error.textContent = message;
  }

  function clearError(fieldId) {
    const input = document.getElementById(fieldId);
    const error = document.getElementById(fieldId + 'Error');
    if (input) input.classList.remove('error');
    if (error) error.textContent = '';
  }

  function clearAllErrors() {
    form.querySelectorAll('.error').forEach(function (el) {
      el.classList.remove('error');
    });
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.textContent = '';
    });
  }

  function validatePhone(value) {
    // South African phone: 10 digits, optionally starting with +27
    const cleaned = value.replace(/[\s\-()]/g, '');
    return /^(\+27|0)[6-8][0-9]{8}$/.test(cleaned);
  }

  function validateEmail(value) {
    return !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function validateForm() {
    clearAllErrors();
    let valid = true;

    const firstName    = document.getElementById('firstName').value.trim();
    const lastName     = document.getElementById('lastName').value.trim();
    const phone        = document.getElementById('phone').value.trim();
    const email        = document.getElementById('email').value.trim();
    const service      = document.getElementById('service').value;
    const location     = document.getElementById('location').value;
    const prefDate     = document.getElementById('preferredDate').value;
    const prefTime     = document.getElementById('preferredTime').value;
    const depositAgree = document.getElementById('depositAgree').checked;

    if (!firstName) { showError('firstName', 'Please enter your first name.'); valid = false; }
    if (!lastName)  { showError('lastName',  'Please enter your last name.'); valid = false; }

    if (!phone) {
      showError('phone', 'Please enter a contact number.'); valid = false;
    } else if (!validatePhone(phone)) {
      showError('phone', 'Please enter a valid South African phone number.'); valid = false;
    }

    if (email && !validateEmail(email)) {
      showError('email', 'Please enter a valid email address.'); valid = false;
    }

    if (!service)  { showError('service',  'Please select a service.'); valid = false; }

    var subTypeRowEl = document.getElementById('subTypeRow');
    if (subTypeRowEl && !subTypeRowEl.hidden) {
      var subTypeVal = document.getElementById('subType') ? document.getElementById('subType').value : '';
      if (!subTypeVal) { showError('subType', 'Please select a style/type.'); valid = false; }
      var lengthGrpEl = document.getElementById('lengthGroup');
      if (lengthGrpEl && !lengthGrpEl.hidden) {
        var hairLengthVal = document.getElementById('hairLength') ? document.getElementById('hairLength').value : '';
        if (!hairLengthVal) { showError('hairLength', 'Please select a braid length.'); valid = false; }
      }
    }

    if (!location) { showError('location', 'Please select a location.'); valid = false; }
    if (!prefDate) { showError('preferredDate', 'Please choose a preferred date.'); valid = false; }
    if (!prefTime) { showError('preferredTime', 'Please choose a preferred time.'); valid = false; }

    if (!depositAgree) {
      showError('depositAgree', 'You must agree to the deposit policy to proceed.'); valid = false;
    }

    return valid;
  }

  // Live clear errors on change
  ['firstName','lastName','phone','email','service','subType','hairLength','location','preferredDate','preferredTime'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', function () { clearError(id); });
  });

  // Stored WhatsApp URL — set on review step, sent on confirm
  var _pendingWaURL = '';

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateForm()) {
      var firstError = form.querySelector('.error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
      }
      return;
    }

    // Collect form values
    var firstName    = document.getElementById('firstName').value.trim();
    var lastName     = document.getElementById('lastName').value.trim();
    var phone        = document.getElementById('phone').value.trim();
    var email        = document.getElementById('email').value.trim();
    var serviceEl    = document.getElementById('service');
    var service      = serviceEl.options[serviceEl.selectedIndex].text;
    var locationEl   = document.getElementById('location');
    var locText      = locationEl.options[locationEl.selectedIndex].text;
    var prefDate     = document.getElementById('preferredDate').value;
    var timeEl       = document.getElementById('preferredTime');
    var prefTime     = timeEl.options[timeEl.selectedIndex].text;
    var notes        = document.getElementById('notes').value.trim();

    var subTypeRowEl2  = document.getElementById('subTypeRow');
    var subTypeEl2     = document.getElementById('subType');
    var subTypeTxt     = (subTypeRowEl2 && !subTypeRowEl2.hidden && subTypeEl2 && subTypeEl2.value)
      ? subTypeEl2.options[subTypeEl2.selectedIndex].text : '';
    var lengthGrpEl2   = document.getElementById('lengthGroup');
    var hairLengthEl2  = document.getElementById('hairLength');
    var hairLengthTxt  = (hairLengthEl2 && lengthGrpEl2 && !lengthGrpEl2.hidden && hairLengthEl2.value)
      ? hairLengthEl2.options[hairLengthEl2.selectedIndex].text : '';

    // Format date for display
    var displayDate = prefDate;
    try {
      displayDate = new Date(prefDate + 'T00:00:00').toLocaleDateString('en-ZA', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
      });
    } catch (ex) {}

    // Build WhatsApp message
    var message = [
      '--- DIOS Booking Request ---',
      'Name: ' + firstName + ' ' + lastName,
      'Phone: ' + phone,
      email         ? 'Email: '   + email         : '',
      'Service: '   + service,
      subTypeTxt    ? 'Style: '   + subTypeTxt    : '',
      hairLengthTxt ? 'Length: '  + hairLengthTxt : '',
      'Location: '  + locText,
      'Date: '      + prefDate,
      'Time: '      + prefTime,
      notes         ? 'Notes: '   + notes         : '',
      '----------------------------',
      'Deposit policy agreed: Yes'
    ].filter(Boolean).join('\n');

    _pendingWaURL = 'https://wa.me/27732668348?text=' + encodeURIComponent(message);

    // Populate on-page summary table
    var summaryDetails = document.getElementById('summaryDetails');
    if (summaryDetails) {
      var rows = [
        ['Name',     firstName + ' ' + lastName],
        ['Phone',    phone],
        email         ? ['Email',    email]         : null,
        ['Service',  service],
        subTypeTxt    ? ['Style',    subTypeTxt]    : null,
        hairLengthTxt ? ['Length',   hairLengthTxt] : null,
        ['Location', locText],
        ['Date',     displayDate],
        ['Time',     prefTime],
        notes         ? ['Notes',    notes]         : null
      ].filter(Boolean);
      summaryDetails.innerHTML = rows.map(function (r) {
        return '<div class="summary-row"><span class="summary-key">' + r[0] + '</span><span class="summary-val">' + r[1] + '</span></div>';
      }).join('');
    }

    // Show summary step, hide form
    var bookingSummary = document.getElementById('bookingSummary');
    if (bookingSummary) {
      form.hidden = true;
      bookingSummary.hidden = false;
      bookingSummary.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  // Edit button — go back to form
  var editBookingBtn = document.getElementById('editBookingBtn');
  if (editBookingBtn) {
    editBookingBtn.addEventListener('click', function () {
      var bookingSummary = document.getElementById('bookingSummary');
      if (bookingSummary) bookingSummary.hidden = true;
      form.hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // Confirm button — open WhatsApp and show confirmed state
  var confirmBookingBtn = document.getElementById('confirmBookingBtn');
  if (confirmBookingBtn) {
    confirmBookingBtn.addEventListener('click', function () {
      window.open(_pendingWaURL, '_blank', 'noopener,noreferrer');
      var bookingSummary  = document.getElementById('bookingSummary');
      var bookingConfirmed = document.getElementById('bookingConfirmed');
      if (bookingSummary)  bookingSummary.hidden  = true;
      if (bookingConfirmed) {
        bookingConfirmed.hidden = false;
        bookingConfirmed.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      form.reset();
    });
  }

  // "Make Another Booking" button — reset everything
  var newBookingBtn = document.getElementById('newBookingBtn');
  if (newBookingBtn) {
    newBookingBtn.addEventListener('click', function () {
      var bookingConfirmed = document.getElementById('bookingConfirmed');
      if (bookingConfirmed) bookingConfirmed.hidden = true;
      form.hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  }

  /* ---- SMOOTH ACTIVE NAV HIGHLIGHTING ---- */
  const sections  = document.querySelectorAll('section[id]');
  const navAnchors = document.querySelectorAll('.nav-links a[href^="#"]');

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        navAnchors.forEach(function (a) {
          a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id);
        });
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });

  sections.forEach(function (s) { observer.observe(s); });

  /* ---- SCROLL REVEAL ---- */
  var revealTargets = document.querySelectorAll(
    '.service-card, .policy-card, .location-card, .price-group, ' +
    '.booking-steps li, .stat, .hours-card, .gallery-item, ' +
    '.section-header, .about-text, .extras-banner, .contact-chip, ' +
    '.booking-info, .booking-form-wrap'
  );

  revealTargets.forEach(function (el, i) {
    el.classList.add('reveal');
    var mod = i % 4;
    if (mod === 1) el.classList.add('reveal-d1');
    else if (mod === 2) el.classList.add('reveal-d2');
    else if (mod === 3) el.classList.add('reveal-d3');
  });

  var revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

  revealTargets.forEach(function (el) { revealObserver.observe(el); });

  /* ---- FAQ CHATBOT ---- */
  function initFaqChatbot() {
    if (document.getElementById('faqChatbot')) return;

    var chatbot = document.createElement('div');
    chatbot.className = 'chatbot-widget';
    chatbot.id = 'faqChatbot';
    chatbot.innerHTML = [
      '<div class="chatbot-panel" id="chatbotPanel">',
      '  <div class="chatbot-header">',
      '    <div class="chatbot-title">',
      '      <span class="chatbot-title-badge">',
      '        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
      '      </span>',
      '      <div><strong>DIOS Assistant</strong><span>FAQs, bookings and quick guidance</span></div>',
      '    </div>',
      '    <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">&times;</button>',
      '  </div>',
      '  <div class="chatbot-body">',
      '    <div class="chatbot-messages" id="chatbotMessages"></div>',
      '    <div class="chatbot-chip-row" id="chatbotChips"></div>',
      '  </div>',
      '  <form class="chatbot-input-row" id="chatbotForm">',
      '    <input class="chatbot-input" id="chatbotInput" type="text" placeholder="Ask about prices, hours, locations or bookings" />',
      '    <button class="chatbot-send" type="submit">Send</button>',
      '  </form>',
      '</div>',
      '<button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="Open DIOS assistant">',
      '  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>',
      '</button>'
    ].join('');

    document.body.appendChild(chatbot);

    var panel = document.getElementById('chatbotPanel');
    var toggle = document.getElementById('chatbotToggle');
    var closeBtn = document.getElementById('chatbotClose');
    var formEl = document.getElementById('chatbotForm');
    var inputEl = document.getElementById('chatbotInput');
    var messagesEl = document.getElementById('chatbotMessages');
    var chipsEl = document.getElementById('chatbotChips');

    function addMessage(role, content) {
      var message = document.createElement('div');
      message.className = 'chatbot-message ' + role;
      message.innerHTML = content;
      messagesEl.appendChild(message);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderChips(items) {
      chipsEl.innerHTML = '';
      items.forEach(function (item) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'chatbot-chip';
        chip.textContent = item;
        chip.addEventListener('click', function () {
          handlePrompt(item);
        });
        chipsEl.appendChild(chip);
      });
    }

    function getReply(message) {
      var text = (message || '').toLowerCase();

      if (/book|appointment|reserve|schedule/.test(text)) {
        return {
          answer: 'Ready to book? Use the <a href="booking.html">booking page</a> or message DIOS directly on <a href="https://wa.me/27732668348" target="_blank" rel="noopener">WhatsApp</a>. A 50% non-refundable deposit confirms the slot.',
          chips: ['Booking policy', 'Opening hours', 'Services pricing']
        };
      }

      if (/price|cost|how much|quote|pricing/.test(text)) {
        return {
          answer: 'You can view the full <a href="services.html">services and pricing list</a>. Final pricing can vary based on length, texture and complexity, especially for braids, wigs and custom styling.',
          chips: ['Braids', 'Makeup', 'Book now']
        };
      }

      if (/hour|open|close|time/.test(text)) {
        return {
          answer: 'Studio hours are Mon-Wed 09:00-17:30, Thu-Fri 08:00-18:00, Saturday 08:00-17:00 and Sunday 11:00-16:00. Before or after-hours appointments add R200.',
          chips: ['Locations', 'Book now', 'Booking policy']
        };
      }

      if (/where|location|midrand|copperleaf|address/.test(text)) {
        return {
          answer: 'DIOS serves clients in Midrand and Copperleaf. Midrand studio: 5 Liebenberg Road, Noordwyk. Copperleaf studio is inside Copperleaf Golf & Country Estate and is appointment only.',
          chips: ['Opening hours', 'Book now', 'WhatsApp']
        };
      }

      if (/policy|deposit|refund|cancel/.test(text)) {
        return {
          answer: 'A 50% non-refundable deposit is required to secure every booking. The deposit is deducted from the final amount. You can read the full <a href="policy.html">booking policy here</a>.',
          chips: ['Book now', 'Opening hours', 'WhatsApp']
        };
      }

      if (/makeup|glam|bridal|graduation|editorial/.test(text)) {
        return {
          answer: 'DIOS offers bridal makeup, events and functions glam, editorial makeup, everyday glam and graduation makeup. For exact availability or recommendations, it is best to <a href="https://wa.me/27732668348" target="_blank" rel="noopener">WhatsApp the studio</a>.',
          chips: ['Services pricing', 'Book now', 'Locations']
        };
      }

      if (/braid|cornrow|wig|ponytail|hair/.test(text)) {
        return {
          answer: 'Hair services include braids, cornrows, ponytails, wig installations, hair colour and other styling. If you already know the style you want, go straight to <a href="booking.html">Book Now</a>.',
          chips: ['Services pricing', 'Book now', 'Booking policy']
        };
      }

      if (/whatsapp|call|phone|contact/.test(text)) {
        return {
          answer: 'You can contact DIOS on <a href="https://wa.me/27732668348" target="_blank" rel="noopener">WhatsApp 073 266 8348</a> or call <a href="tel:0105007562">010 500 7562</a>.',
          chips: ['Book now', 'Locations', 'Opening hours']
        };
      }

      return {
        answer: 'I can help with bookings, pricing, locations, hours, makeup, hair services and booking policy. If you want to secure a slot, head to <a href="booking.html">Book Now</a>.',
        chips: ['Book now', 'Services pricing', 'Opening hours', 'Locations']
      };
    }

    function handlePrompt(prompt) {
      addMessage('user', prompt);
      var reply = getReply(prompt);
      window.setTimeout(function () {
        addMessage('bot', reply.answer);
        renderChips(reply.chips);
      }, 180);
    }

    toggle.addEventListener('click', function () {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) {
        inputEl.focus();
      }
    });

    closeBtn.addEventListener('click', function () {
      panel.classList.remove('open');
    });

    formEl.addEventListener('submit', function (e) {
      e.preventDefault();
      var value = inputEl.value.trim();
      if (!value) return;
      inputEl.value = '';
      handlePrompt(value);
    });

    addMessage('bot', 'Hi, I\'m the DIOS assistant. Ask me about prices, studio hours, locations, booking policy or the best way to book.');
    renderChips(['Book now', 'Services pricing', 'Opening hours', 'Locations']);
  }

  initFaqChatbot();

})();
