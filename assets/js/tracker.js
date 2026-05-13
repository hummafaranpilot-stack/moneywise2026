/*!
 * ============================================================================
 * Money Wise 2026 — Visitor Tracker
 * Collects browser fingerprint, behavior, and reports to /api/log.php
 * ============================================================================
 *  - Visitor ID persisted in localStorage (returning user detection)
 *  - Session ID rotates per visit
 *  - Sends to backend after 2s, on heartbeat (every 30s), and on unload
 * ============================================================================
 */
(function () {
  'use strict';

  // Skip self-tracking for /api/ and /report/ paths
  var path = location.pathname || '/';
  if (path.indexOf('/api/') === 0 || path.indexOf('/report/') === 0) return;

  var API_ENDPOINT = '/api/log.php';
  var HEARTBEAT_MS = 30000;
  var INITIAL_DELAY_MS = 2000;

  // ---------------- Visitor / Session IDs ----------------
  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }
  function getVisitorId() {
    try {
      var id = localStorage.getItem('mw_vid');
      if (!id) { id = uuid(); localStorage.setItem('mw_vid', id); }
      return id;
    } catch (e) {
      return uuid();
    }
  }
  function getSessionId() {
    try {
      var sid = sessionStorage.getItem('mw_sid');
      if (!sid) { sid = uuid(); sessionStorage.setItem('mw_sid', sid); }
      return sid;
    } catch (e) {
      return uuid();
    }
  }

  // ---------------- Hashing (simple, no crypto API needed) ----------------
  function hashString(str) {
    var hash = 0;
    if (!str) return '0';
    for (var i = 0; i < str.length; i++) {
      var ch = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + ch;
      hash |= 0;
    }
    // Force unsigned and convert to base36 for compactness
    return (hash >>> 0).toString(36);
  }

  // ---------------- Canvas fingerprint ----------------
  function getCanvasFingerprint() {
    try {
      var canvas = document.createElement('canvas');
      canvas.width = 240; canvas.height = 60;
      var ctx = canvas.getContext('2d');
      ctx.textBaseline = 'top';
      ctx.font = '14px "Arial"';
      ctx.fillStyle = '#069';
      ctx.fillText('MoneyWise 🪙 2026 — fingerprint', 2, 2);
      ctx.strokeStyle = 'rgba(102,204,0,0.7)';
      ctx.beginPath();
      ctx.arc(50, 30, 20, 0, Math.PI * 2, true); ctx.stroke();
      ctx.fillStyle = 'rgba(255,0,255,0.5)';
      ctx.fillRect(60, 10, 40, 25);
      return hashString(canvas.toDataURL());
    } catch (e) { return ''; }
  }

  // ---------------- WebGL info ----------------
  function getWebGLInfo() {
    try {
      var canvas = document.createElement('canvas');
      var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (!gl) return { renderer: '', vendor: '', version: '' };
      var dbg = gl.getExtension('WEBGL_debug_renderer_info');
      var renderer = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
      var vendor   = dbg ? gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL)   : gl.getParameter(gl.VENDOR);
      var version  = gl.getParameter(gl.VERSION);
      return {
        renderer: (renderer || '').toString().substring(0, 200),
        vendor:   (vendor   || '').toString().substring(0, 200),
        version:  (version  || '').toString().substring(0, 100),
      };
    } catch (e) { return { renderer: '', vendor: '', version: '' }; }
  }

  // ---------------- Audio fingerprint ----------------
  function getAudioFingerprint() {
    return new Promise(function (resolve) {
      try {
        var AC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!AC) return resolve('');
        var ctx = new AC(1, 44100, 44100);
        var osc = ctx.createOscillator(); osc.type = 'triangle'; osc.frequency.value = 1000;
        var comp = ctx.createDynamicsCompressor();
        comp.threshold.value = -50; comp.knee.value = 40;
        comp.ratio.value = 12; comp.attack.value = 0; comp.release.value = 0.25;
        osc.connect(comp); comp.connect(ctx.destination); osc.start();
        ctx.startRendering();
        var done = false;
        var timer = setTimeout(function () { if (!done) resolve(''); }, 1500);
        ctx.oncomplete = function (e) {
          done = true; clearTimeout(timer);
          var buf = e.renderedBuffer.getChannelData(0);
          var sum = 0;
          for (var i = 0; i < buf.length; i++) sum += Math.abs(buf[i]);
          resolve(hashString(sum.toString()));
        };
      } catch (e) { resolve(''); }
    });
  }

  // ---------------- Font detection (probabilistic) ----------------
  function detectFonts() {
    var baseFonts = ['monospace', 'sans-serif', 'serif'];
    var testFonts = [
      'Arial','Arial Black','Arial Narrow','Arial Rounded MT Bold','Calibri','Cambria',
      'Candara','Comic Sans MS','Consolas','Courier','Courier New','Georgia','Helvetica',
      'Impact','Lucida Console','Lucida Sans Unicode','Microsoft Sans Serif','Palatino Linotype',
      'Segoe UI','Tahoma','Times','Times New Roman','Trebuchet MS','Verdana','Verdana Pro',
      'Cantarell','DejaVu Sans','Droid Sans','Liberation Sans','Open Sans','Roboto','Ubuntu',
      'Optima','Futura','Geneva','Gill Sans','Monaco','Apple SD Gothic Neo','Hiragino Sans',
      'PT Sans','PT Serif','Source Sans Pro','Source Code Pro','Inter','Lato','Montserrat',
      'Noto Sans','Poppins','Raleway','Playfair Display','Merriweather'
    ];
    var testStr = 'mmmmmmmmmmlli'; var testSize = '72px';
    var h = document.getElementsByTagName('body')[0];
    var s = document.createElement('span');
    s.style.fontSize = testSize; s.style.position = 'absolute';
    s.style.left = '-9999px'; s.innerHTML = testStr;
    h.appendChild(s);
    var defaultWidth = {}, defaultHeight = {};
    for (var i = 0; i < baseFonts.length; i++) {
      s.style.fontFamily = baseFonts[i];
      defaultWidth[baseFonts[i]] = s.offsetWidth;
      defaultHeight[baseFonts[i]] = s.offsetHeight;
    }
    var detected = [];
    for (var j = 0; j < testFonts.length; j++) {
      var f = testFonts[j], present = false;
      for (var k = 0; k < baseFonts.length; k++) {
        s.style.fontFamily = "'" + f + "'," + baseFonts[k];
        if (s.offsetWidth !== defaultWidth[baseFonts[k]] || s.offsetHeight !== defaultHeight[baseFonts[k]]) {
          present = true; break;
        }
      }
      if (present) detected.push(f);
    }
    h.removeChild(s);
    return detected;
  }

  // ---------------- Browser state ----------------
  function isCookiesEnabled() {
    try {
      document.cookie = 'mw_test=1; SameSite=Lax';
      var ok = document.cookie.indexOf('mw_test=') !== -1;
      document.cookie = 'mw_test=; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
      return ok;
    } catch (e) { return false; }
  }
  function isLocalStorageEnabled() {
    try { localStorage.setItem('mw_t','1'); localStorage.removeItem('mw_t'); return true; }
    catch (e) { return false; }
  }
  function isSessionStorageEnabled() {
    try { sessionStorage.setItem('mw_t','1'); sessionStorage.removeItem('mw_t'); return true; }
    catch (e) { return false; }
  }
  function isIndexedDBEnabled() { return !!window.indexedDB; }

  // ---------------- Incognito detection (heuristic) ----------------
  function isIncognito() {
    return new Promise(function (resolve) {
      try {
        if (navigator.storage && navigator.storage.estimate) {
          navigator.storage.estimate().then(function (e) {
            // Incognito typically has tiny quota (< 120 MB)
            resolve((e.quota || 0) < 120000000);
          }).catch(function () { resolve(false); });
        } else {
          resolve(false);
        }
      } catch (e) { resolve(false); }
    });
  }

  // ---------------- Battery API ----------------
  function getBattery() {
    return new Promise(function (resolve) {
      if (!navigator.getBattery) return resolve({ level: null, charging: null });
      try {
        navigator.getBattery().then(function (b) {
          resolve({ level: b.level, charging: b.charging });
        }).catch(function () { resolve({ level: null, charging: null }); });
      } catch (e) { resolve({ level: null, charging: null }); }
    });
  }

  // ---------------- User-Agent parsing (light) ----------------
  function parseUA() {
    var ua = navigator.userAgent;
    var browser = 'Unknown', browserVer = '', os = 'Unknown', osVer = '', device = 'desktop';
    if (/Edg\/([\d.]+)/.test(ua))                 { browser = 'Edge';    browserVer = RegExp.$1; }
    else if (/OPR\/([\d.]+)/.test(ua))            { browser = 'Opera';   browserVer = RegExp.$1; }
    else if (/Firefox\/([\d.]+)/.test(ua))        { browser = 'Firefox'; browserVer = RegExp.$1; }
    else if (/Chrome\/([\d.]+)/.test(ua))         { browser = 'Chrome';  browserVer = RegExp.$1; }
    else if (/Version\/([\d.]+).*Safari/.test(ua)){ browser = 'Safari';  browserVer = RegExp.$1; }

    if (/Windows NT ([\d.]+)/.test(ua))           { os = 'Windows'; osVer = RegExp.$1; }
    else if (/Mac OS X ([\d_]+)/.test(ua))        { os = 'macOS';   osVer = RegExp.$1.replace(/_/g, '.'); }
    else if (/Android ([\d.]+)/.test(ua))         { os = 'Android'; osVer = RegExp.$1; device = 'mobile'; }
    else if (/iPhone OS ([\d_]+)/.test(ua))       { os = 'iOS';     osVer = RegExp.$1.replace(/_/g, '.'); device = 'mobile'; }
    else if (/iPad.*OS ([\d_]+)/.test(ua))        { os = 'iPadOS';  osVer = RegExp.$1.replace(/_/g, '.'); device = 'tablet'; }
    else if (/Linux/.test(ua))                    { os = 'Linux'; }

    if (device === 'desktop' && /Mobile/.test(ua)) device = 'mobile';
    return { browser_name: browser, browser_version: browserVer, os_name: os, os_version: osVer, device_type: device };
  }

  // ---------------- Bot / WebDriver detection ----------------
  function isWebDriver() {
    return !!(navigator.webdriver ||
      window._phantom || window.callPhantom || window.__nightmare ||
      (window.chrome && window.chrome.runtime && window.chrome.runtime.onMessage === undefined && navigator.webdriver));
  }
  function isLikelyBot() {
    var ua = navigator.userAgent.toLowerCase();
    var botPatterns = ['headlesschrome', 'phantomjs', 'selenium', 'puppeteer', 'playwright', 'bot', 'crawler', 'spider'];
    for (var i = 0; i < botPatterns.length; i++) if (ua.indexOf(botPatterns[i]) !== -1) return true;
    return false;
  }

  // ---------------- Behavior tracking ----------------
  var startTime = Date.now();
  var behavior = {
    session_duration: 0,
    scroll_depth_max: 0,
    mouse_movements: 0,
    clicks_count: 0,
    keystrokes_count: 0,
    tab_switches: 0,
    pages_viewed: 1,
  };
  var lastMouseLog = 0;

  function trackMouseMove() { behavior.mouse_movements++; }
  function trackClick()     { behavior.clicks_count++; }
  function trackKey()       { behavior.keystrokes_count++; }
  function trackScroll() {
    var docHeight = Math.max(
      document.documentElement.scrollHeight,
      document.body.scrollHeight,
      document.documentElement.offsetHeight
    );
    var winHeight = window.innerHeight || document.documentElement.clientHeight;
    var scrolled = (window.pageYOffset || document.documentElement.scrollTop || 0) + winHeight;
    var pct = Math.min(100, Math.round((scrolled / docHeight) * 100));
    if (pct > behavior.scroll_depth_max) behavior.scroll_depth_max = pct;
  }
  function trackVisibility() {
    if (document.hidden) behavior.tab_switches++;
  }
  function updateDuration() {
    behavior.session_duration = Math.floor((Date.now() - startTime) / 1000);
  }

  // Throttle mouse events to avoid runaway counts
  document.addEventListener('mousemove', function () {
    var now = Date.now();
    if (now - lastMouseLog > 100) { lastMouseLog = now; trackMouseMove(); }
  }, { passive: true });
  document.addEventListener('click', trackClick, { passive: true });
  document.addEventListener('keydown', trackKey, { passive: true });
  document.addEventListener('scroll', trackScroll, { passive: true });
  document.addEventListener('visibilitychange', trackVisibility);

  // ---------------- Collect all fingerprint data ----------------
  function collectFingerprint() {
    var uaParts = parseUA();
    var webgl = getWebGLInfo();
    var fonts = detectFonts();
    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || {};
    return {
      visitor_id: getVisitorId(),
      session_id: getSessionId(),

      // Browser
      user_agent:        navigator.userAgent,
      browser_name:      uaParts.browser_name,
      browser_version:   uaParts.browser_version,
      os_name:           uaParts.os_name,
      os_version:        uaParts.os_version,
      device_type:       uaParts.device_type,
      languages:         navigator.languages || [navigator.language],
      language_primary:  navigator.language || '',
      timezone:          Intl && Intl.DateTimeFormat ? Intl.DateTimeFormat().resolvedOptions().timeZone : '',
      timezone_offset:   new Date().getTimezoneOffset(),

      // Screen
      screen_width:       screen.width,
      screen_height:      screen.height,
      screen_avail_width: screen.availWidth,
      screen_avail_height:screen.availHeight,
      screen_color_depth: screen.colorDepth,
      pixel_ratio:        window.devicePixelRatio || 1,
      viewport_width:     window.innerWidth,
      viewport_height:    window.innerHeight,

      // Hardware
      cpu_cores:        navigator.hardwareConcurrency || 0,
      device_memory:    navigator.deviceMemory || 0,
      touch_support:    'ontouchstart' in window || navigator.maxTouchPoints > 0,
      max_touch_points: navigator.maxTouchPoints || 0,

      // WebGL
      webgl_renderer: webgl.renderer,
      webgl_vendor:   webgl.vendor,
      webgl_version:  webgl.version,

      // Canvas
      canvas_fingerprint: getCanvasFingerprint(),

      // Fonts
      fonts_count: fonts.length,
      fonts_list:  fonts.slice(0, 30),

      // Plugins (legacy)
      plugins_count: navigator.plugins ? navigator.plugins.length : 0,

      // State
      cookies_enabled:         isCookiesEnabled(),
      local_storage_enabled:   isLocalStorageEnabled(),
      session_storage_enabled: isSessionStorageEnabled(),
      indexed_db_enabled:      isIndexedDBEnabled(),
      do_not_track:            navigator.doNotTrack || navigator.msDoNotTrack || (window.doNotTrack ? '1' : '0'),
      is_webdriver:            isWebDriver(),
      is_bot:                  isLikelyBot(),

      // Network
      connection_type: conn.type || '',
      effective_type:  conn.effectiveType || '',
      downlink:        typeof conn.downlink === 'number' ? conn.downlink : null,
      rtt:             typeof conn.rtt === 'number' ? conn.rtt : null,
      save_data:       !!conn.saveData,

      // Referrer & page
      referrer:      document.referrer || '',
      landing_page:  location.href,
      page_url:      location.href,
      page_title:    document.title || '',
    };
  }

  // ---------------- Send to backend ----------------
  function send(payload, useBeacon) {
    var body = JSON.stringify(payload);
    if (useBeacon && navigator.sendBeacon) {
      try {
        var blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon(API_ENDPOINT, blob);
        return;
      } catch (e) { /* fall through to fetch */ }
    }
    try {
      fetch(API_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'omit',
      }).catch(function () { /* silent */ });
    } catch (e) { /* silent */ }
  }

  // ---------------- Async data assembly + send ----------------
  function sendFull(useBeacon) {
    updateDuration();
    var base = collectFingerprint();
    // Merge behavior
    for (var k in behavior) if (Object.prototype.hasOwnProperty.call(behavior, k)) base[k] = behavior[k];

    // Async additions (incognito + battery + audio)
    Promise.all([isIncognito(), getBattery(), getAudioFingerprint()])
      .then(function (results) {
        base.is_incognito      = results[0];
        base.battery_level     = results[1].level;
        base.battery_charging  = results[1].charging;
        base.audio_fingerprint = results[2];
        send(base, useBeacon);
      })
      .catch(function () { send(base, useBeacon); });
  }

  // ---------------- Lightweight heartbeat (no fingerprint re-collection) ----------------
  function sendHeartbeat() {
    updateDuration();
    var payload = {
      visitor_id: getVisitorId(),
      session_id: getSessionId(),
      page_url: location.href,
      page_title: document.title || '',
      user_agent: navigator.userAgent,
      // Behavior delta
      session_duration: behavior.session_duration,
      scroll_depth_max: behavior.scroll_depth_max,
      mouse_movements:  behavior.mouse_movements,
      clicks_count:     behavior.clicks_count,
      keystrokes_count: behavior.keystrokes_count,
      tab_switches:     behavior.tab_switches,
      pages_viewed:     behavior.pages_viewed,
    };
    send(payload, false);
  }

  // ---------------- Schedule ----------------
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function () { sendFull(false); }, INITIAL_DELAY_MS);
  } else {
    window.addEventListener('DOMContentLoaded', function () {
      setTimeout(function () { sendFull(false); }, INITIAL_DELAY_MS);
    });
  }

  setInterval(sendHeartbeat, HEARTBEAT_MS);

  window.addEventListener('beforeunload', function () { sendHeartbeat(); /* uses fetch keepalive */ });
  window.addEventListener('pagehide',     function () { sendHeartbeat(); });

})();
