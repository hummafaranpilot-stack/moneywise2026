/*!
 * ============================================================================
 * Money Wise 2026 — Visitor Tracker v2 (150+ data points)
 * Collects: Browser, Screen, Hardware, Network, Timezone, WebGL, Canvas,
 *           Audio, Fonts, Plugins, Storage, Permissions, Speech, Media,
 *           WebRTC, Codecs, Features, Page, Behavior, Incognito.
 * Sends to /api/log.php on initial load, every 30s, and final beacon on unload.
 * Skips on /report/* and /api/* paths.
 * ============================================================================
 */
(function () {
'use strict';

// ----- skip self-tracking -----
var path = window.location.pathname || '/';
if (path.indexOf('/report') === 0 || path.indexOf('/api/') === 0) return;

// ============================================================================
//   HELPERS
// ============================================================================

function hashString(str) {
  let hash = 0;
  if (!str) return '0';
  for (let i = 0; i < str.length; i++) {
    const ch = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + ch;
    hash = hash & hash;
  }
  return Math.abs(hash).toString(36);
}

function getVisitorId() {
  try {
    let id = localStorage.getItem('mw_vid');
    if (!id) {
      id = 'v_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
      localStorage.setItem('mw_vid', id);
    }
    return id;
  } catch (e) { return 'v_' + Date.now().toString(36); }
}

function getSessionId() {
  try {
    let id = sessionStorage.getItem('mw_sid');
    if (!id) {
      id = 's_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
      sessionStorage.setItem('mw_sid', id);
    }
    return id;
  } catch (e) { return 's_' + Date.now().toString(36); }
}

function parseUA(ua) {
  const browsers = {
    'Edg/': 'Edge', 'OPR/': 'Opera', 'Chrome/': 'Chrome',
    'Safari/': 'Safari', 'Firefox/': 'Firefox', 'MSIE': 'IE', 'Trident/': 'IE'
  };
  let browser = 'Unknown', version = 'Unknown';
  for (const key in browsers) {
    if (ua.indexOf(key) !== -1) {
      browser = browsers[key];
      const m = ua.match(new RegExp(key.replace('/', '\\/') + '(\\d+(?:\\.\\d+)?)'));
      if (m) version = m[1];
      break;
    }
  }
  let os = 'Unknown', osVersion = 'Unknown';
  if (/Windows NT (\d+\.\d+)/.test(ua))         { os = 'Windows'; osVersion = RegExp.$1; }
  else if (/Mac OS X (\d+[._]\d+)/.test(ua))    { os = 'macOS';   osVersion = RegExp.$1.replace(/_/g, '.'); }
  else if (/Android (\d+(?:\.\d+)?)/.test(ua))  { os = 'Android'; osVersion = RegExp.$1; }
  else if (/(?:iPhone|iPad).+OS (\d+[._]\d+)/.test(ua)) { os = 'iOS'; osVersion = RegExp.$1.replace(/_/g, '.'); }
  else if (ua.indexOf('Linux') !== -1)          { os = 'Linux'; }
  let deviceType = 'desktop';
  if (/Mobile|Android|iPhone/.test(ua)) deviceType = 'mobile';
  else if (/iPad|Tablet/.test(ua))      deviceType = 'tablet';
  return { browser, version, os, osVersion, deviceType };
}

function testStorage(type) {
  try { const s = window[type]; s.setItem('__t__', '__t__'); s.removeItem('__t__'); return true; }
  catch (e) { return false; }
}

// ============================================================================
//   SECTION A: BROWSER INFO
// ============================================================================
function getBrowserInfo() {
  const parsed = parseUA(navigator.userAgent);
  return {
    user_agent:         navigator.userAgent,
    app_name:           navigator.appName,
    app_version:        navigator.appVersion,
    product:            navigator.product,
    product_sub:        navigator.productSub,
    vendor:             navigator.vendor,
    vendor_sub:         navigator.vendorSub || '',
    platform:           navigator.platform,
    browser_name:       parsed.browser,
    browser_version:    parsed.version,
    os_name:            parsed.os,
    os_version:         parsed.osVersion,
    device_type:        parsed.deviceType,
    language:           navigator.language,
    languages:          navigator.languages ? Array.from(navigator.languages) : [],
    online:             navigator.onLine,
    cookie_enabled:     navigator.cookieEnabled,
    do_not_track:       navigator.doNotTrack || 'unspecified',
    webdriver:          navigator.webdriver === true,
    pdf_viewer_enabled: navigator.pdfViewerEnabled || false,
    java_enabled:       typeof navigator.javaEnabled === 'function' ? navigator.javaEnabled() : false,
    max_touch_points:   navigator.maxTouchPoints || 0,
    is_brave:           typeof navigator.brave !== 'undefined',
  };
}

// ============================================================================
//   SECTION B: SCREEN INFO
// ============================================================================
function getScreenInfo() {
  return {
    screen_width:             screen.width,
    screen_height:            screen.height,
    screen_avail_width:       screen.availWidth,
    screen_avail_height:      screen.availHeight,
    screen_color_depth:       screen.colorDepth,
    screen_pixel_depth:       screen.pixelDepth,
    window_inner_width:       window.innerWidth,
    window_inner_height:      window.innerHeight,
    window_outer_width:       window.outerWidth,
    window_outer_height:      window.outerHeight,
    device_pixel_ratio:       window.devicePixelRatio || 1,
    pixel_ratio:              window.devicePixelRatio || 1,
    viewport_width:           window.innerWidth,
    viewport_height:          window.innerHeight,
    screen_orientation_type:  screen.orientation ? screen.orientation.type : null,
    screen_orientation_angle: screen.orientation ? screen.orientation.angle : null,
    prefers_color_scheme:     window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
    prefers_reduced_motion:   window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    color_gamut_p3:           window.matchMedia && window.matchMedia('(color-gamut: p3)').matches,
    color_gamut_srgb:         window.matchMedia && window.matchMedia('(color-gamut: srgb)').matches,
  };
}

// ============================================================================
//   SECTION C: HARDWARE
// ============================================================================
async function getHardwareInfo() {
  let batt = null;
  if ('getBattery' in navigator) {
    try {
      const b = await navigator.getBattery();
      batt = {
        battery_level:            b.level,
        battery_charging:         b.charging,
        battery_charging_time:    isFinite(b.chargingTime) ? b.chargingTime : null,
        battery_discharging_time: isFinite(b.dischargingTime) ? b.dischargingTime : null,
      };
    } catch (e) {}
  }
  return Object.assign({
    hardware_concurrency: navigator.hardwareConcurrency || 0,
    cpu_cores:            navigator.hardwareConcurrency || 0,
    device_memory:        navigator.deviceMemory || null,
    touch_support:        ('ontouchstart' in window) || (navigator.maxTouchPoints > 0),
  }, batt || {});
}

// ============================================================================
//   SECTION D: NETWORK
// ============================================================================
function getNetworkInfo() {
  const c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (!c) return { connection_supported: false };
  return {
    connection_supported:      true,
    connection_type:           c.type || null,
    connection_effective_type: c.effectiveType || null,
    effective_type:            c.effectiveType || null,
    connection_downlink:       (typeof c.downlink === 'number') ? c.downlink : null,
    downlink:                  (typeof c.downlink === 'number') ? c.downlink : null,
    connection_rtt:            (typeof c.rtt === 'number') ? c.rtt : null,
    rtt:                       (typeof c.rtt === 'number') ? c.rtt : null,
    connection_save_data:      !!c.saveData,
    save_data:                 !!c.saveData,
  };
}

// ============================================================================
//   SECTION E: TIMEZONE
// ============================================================================
function getTimezoneInfo() {
  const opts = (Intl && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions() : {};
  const off = new Date().getTimezoneOffset();
  return {
    timezone:              opts.timeZone || '',
    timezone_offset:       off,
    timezone_offset_hours: -(off / 60),
    locale:                opts.locale || '',
    calendar:              opts.calendar || '',
    numbering_system:      opts.numberingSystem || '',
  };
}

// ============================================================================
//   SECTION F: WEBGL
// ============================================================================
function getWebGLInfo() {
  try {
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) return { webgl_supported: false };
    const dbg = gl.getExtension('WEBGL_debug_renderer_info');
    return {
      webgl_supported:             true,
      webgl_vendor:                gl.getParameter(gl.VENDOR),
      webgl_renderer:              gl.getParameter(gl.RENDERER),
      webgl_unmasked_vendor:       dbg ? gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL)   : null,
      webgl_unmasked_renderer:     dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : null,
      webgl_version:               gl.getParameter(gl.VERSION),
      webgl_shading_language:      gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
      webgl_extensions:            gl.getSupportedExtensions(),
      webgl_max_texture_size:      gl.getParameter(gl.MAX_TEXTURE_SIZE),
      webgl_max_renderbuffer_size: gl.getParameter(gl.MAX_RENDERBUFFER_SIZE),
    };
  } catch (e) { return { webgl_supported: false }; }
}

// ============================================================================
//   SECTION G: CANVAS
// ============================================================================
function getCanvasFingerprint() {
  try {
    const c = document.createElement('canvas');
    c.width = 300; c.height = 150;
    const ctx = c.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '14px Arial';
    ctx.fillStyle = '#f60';
    ctx.fillRect(125, 1, 62, 20);
    ctx.fillStyle = '#069';
    ctx.fillText('Fingerprint test 🎨', 2, 15);
    ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
    ctx.fillText('Fingerprint test 🎨', 4, 17);
    const url = c.toDataURL();
    const hash = hashString(url);
    return { canvas_supported: true, canvas_hash: hash, canvas_fingerprint: hash };
  } catch (e) { return { canvas_supported: false }; }
}

// ============================================================================
//   SECTION H: AUDIO
// ============================================================================
async function getAudioFingerprint() {
  try {
    const Ctx = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    if (!Ctx) return { audio_supported: false };
    const c = new Ctx(1, 44100, 44100);
    const osc = c.createOscillator();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(10000, c.currentTime);
    const comp = c.createDynamicsCompressor();
    comp.threshold.setValueAtTime(-50, c.currentTime);
    comp.knee.setValueAtTime(40, c.currentTime);
    comp.ratio.setValueAtTime(12, c.currentTime);
    osc.connect(comp); comp.connect(c.destination); osc.start(0);
    const buf = await c.startRendering();
    const s = buf.getChannelData(0);
    let h = 0;
    for (let i = 4500; i < 5000; i++) h += Math.abs(s[i]);
    return { audio_supported: true, audio_fingerprint: h.toString(), audio_sample_rate: c.sampleRate };
  } catch (e) { return { audio_supported: false }; }
}

// ============================================================================
//   SECTION I: FONTS
// ============================================================================
function detectFonts() {
  const base = ['monospace', 'sans-serif', 'serif'];
  const test = 'mmmmmmmmmmlli';
  const fonts = ['Arial','Arial Black','Calibri','Cambria','Comic Sans MS','Consolas','Courier','Courier New','Georgia','Helvetica','Impact','Lucida Console','Microsoft Sans Serif','Palatino','Segoe UI','Tahoma','Times','Times New Roman','Trebuchet MS','Verdana','Roboto','Open Sans','Lato','Montserrat','Source Sans Pro','Playfair Display','Lora','Inter','Avenir','Optima','Helvetica Neue','MS Gothic','Yu Gothic','Noto Sans','Andale Mono','Brush Script MT','Garamond','Geneva','Monaco','Hiragino Sans','PT Sans','PT Serif','Ubuntu','DejaVu Sans','Liberation Sans'];
  const span = document.createElement('span');
  span.style.cssText = 'font-size:72px; position:absolute; left:-9999px; top:-9999px; visibility:hidden;';
  span.textContent = test;
  document.body.appendChild(span);
  const dw = {}, dh = {};
  base.forEach(b => { span.style.fontFamily = b; dw[b] = span.offsetWidth; dh[b] = span.offsetHeight; });
  const detected = [];
  fonts.forEach(f => {
    for (const b of base) {
      span.style.fontFamily = `'${f}', ${b}`;
      if (span.offsetWidth !== dw[b] || span.offsetHeight !== dh[b]) {
        detected.push(f); break;
      }
    }
  });
  document.body.removeChild(span);
  return { fonts_detected: detected, fonts_list: detected, fonts_count: detected.length };
}

// ============================================================================
//   SECTION J: PLUGINS
// ============================================================================
function getPluginsInfo() {
  const plugins = [];
  if (navigator.plugins) {
    for (let i = 0; i < navigator.plugins.length; i++) {
      const p = navigator.plugins[i];
      plugins.push({ name: p.name, filename: p.filename, description: p.description });
    }
  }
  const mimes = [];
  if (navigator.mimeTypes) {
    for (let i = 0; i < navigator.mimeTypes.length; i++) {
      const m = navigator.mimeTypes[i];
      mimes.push({ type: m.type, description: m.description, suffixes: m.suffixes });
    }
  }
  return {
    plugins_list:     plugins,
    plugins_count:    plugins.length,
    mime_types_list:  mimes,
    mime_types_count: mimes.length,
  };
}

// ============================================================================
//   SECTION K: STORAGE
// ============================================================================
function getStorageInfo() {
  const info = {
    cookies_enabled:           navigator.cookieEnabled,
    cookies_string:            document.cookie,
    cookies_count:             document.cookie ? document.cookie.split(';').filter(c => c.trim()).length : 0,
    localstorage_supported:    testStorage('localStorage'),
    local_storage_enabled:     testStorage('localStorage'),
    sessionstorage_supported:  testStorage('sessionStorage'),
    session_storage_enabled:   testStorage('sessionStorage'),
    indexeddb_supported:       'indexedDB' in window,
    indexed_db_enabled:        'indexedDB' in window,
    service_worker_supported:  'serviceWorker' in navigator,
    cache_supported:           'caches' in window,
    localstorage_keys:         [],
    localstorage_size:         0,
    sessionstorage_keys:       [],
  };
  if (info.localstorage_supported) {
    try {
      info.localstorage_keys = Object.keys(localStorage);
      info.localstorage_size = JSON.stringify(localStorage).length;
    } catch (e) {}
  }
  if (info.sessionstorage_supported) {
    try { info.sessionstorage_keys = Object.keys(sessionStorage); } catch (e) {}
  }
  return info;
}

async function getStorageEstimate() {
  if ('storage' in navigator && 'estimate' in navigator.storage) {
    try { const e = await navigator.storage.estimate(); return { storage_quota: e.quota, storage_usage: e.usage }; }
    catch (e) {}
  }
  return null;
}

// ============================================================================
//   SECTION L: PERMISSIONS
// ============================================================================
async function getPermissionsInfo() {
  if (!navigator.permissions) return { permissions_supported: false };
  const list = ['geolocation','notifications','camera','microphone','midi','background-sync','persistent-storage','clipboard-read','clipboard-write'];
  const out = {};
  for (const p of list) {
    try { const r = await navigator.permissions.query({ name: p }); out[p] = r.state; }
    catch (e) { out[p] = 'not_supported'; }
  }
  return { permissions_supported: true, permissions_state: out };
}

// ============================================================================
//   SECTION M: MEDIA DEVICES
// ============================================================================
async function getMediaDevicesInfo() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return { media_devices_supported: false };
  try {
    const d = await navigator.mediaDevices.enumerateDevices();
    return {
      media_devices_supported: true,
      media_devices_list:      d.map(x => ({ kind: x.kind, label: x.label || '(hidden)' })),
      audio_inputs:            d.filter(x => x.kind === 'audioinput').length,
      audio_outputs:           d.filter(x => x.kind === 'audiooutput').length,
      video_inputs:            d.filter(x => x.kind === 'videoinput').length,
    };
  } catch (e) { return { media_devices_supported: false }; }
}

// ============================================================================
//   SECTION N: WEBRTC IPs
// ============================================================================
function getWebRTCIPs() {
  return new Promise(resolve => {
    if (!window.RTCPeerConnection) return resolve({ webrtc_supported: false });
    const ips = new Set();
    let pc;
    let resolved = false;
    const finish = () => {
      if (resolved) return; resolved = true;
      try { if (pc) pc.close(); } catch(e){}
      resolve({ webrtc_supported: true, webrtc_ips: Array.from(ips), webrtc_ip_count: ips.size });
    };
    try {
      pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
      pc.createDataChannel('');
      pc.createOffer().then(o => pc.setLocalDescription(o)).catch(() => {});
      pc.onicecandidate = e => {
        if (!e.candidate) { finish(); return; }
        const m = (e.candidate.candidate || '').match(/(\d+\.\d+\.\d+\.\d+|[a-f0-9]+:[a-f0-9:]+)/i);
        if (m) ips.add(m[1]);
      };
      setTimeout(finish, 1500);
    } catch (e) { resolve({ webrtc_supported: false }); }
  });
}

// ============================================================================
//   SECTION O: SPEECH VOICES
// ============================================================================
function getSpeechVoices() {
  if (!('speechSynthesis' in window)) return { speech_supported: false };
  let v = window.speechSynthesis.getVoices();
  return {
    speech_supported:     true,
    speech_voices_count:  v.length,
    speech_voices_list:   v.slice(0, 20).map(x => ({ name: x.name, lang: x.lang, local: x.localService, default: x.default })),
  };
}

// ============================================================================
//   SECTION P: CODECS
// ============================================================================
function getCodecSupport() {
  const v = document.createElement('video');
  const a = document.createElement('audio');
  return {
    codec_video_h264:     v.canPlayType('video/mp4; codecs="avc1.42E01E"'),
    codec_video_webm_vp8: v.canPlayType('video/webm; codecs="vp8, vorbis"'),
    codec_video_webm_vp9: v.canPlayType('video/webm; codecs="vp9"'),
    codec_video_av1:      v.canPlayType('video/mp4; codecs="av01.0.05M.08"'),
    codec_video_hevc:     v.canPlayType('video/mp4; codecs="hev1.1.6.L93.B0"'),
    codec_audio_aac:      a.canPlayType('audio/mp4; codecs="mp4a.40.2"'),
    codec_audio_mp3:      a.canPlayType('audio/mpeg'),
    codec_audio_ogg:      a.canPlayType('audio/ogg; codecs="vorbis"'),
    codec_audio_opus:     a.canPlayType('audio/ogg; codecs="opus"'),
    codec_audio_flac:     a.canPlayType('audio/flac'),
  };
}

// ============================================================================
//   SECTION Q: FEATURES
// ============================================================================
function getFeatureSupport() {
  let webgl1 = false, webgl2 = false;
  try { webgl1 = !!document.createElement('canvas').getContext('webgl'); } catch(e){}
  try { webgl2 = !!document.createElement('canvas').getContext('webgl2'); } catch(e){}
  return {
    has_local_storage:     'localStorage' in window,
    has_indexed_db:        'indexedDB' in window,
    has_service_worker:    'serviceWorker' in navigator,
    has_push_manager:      'PushManager' in window,
    has_notification:      'Notification' in window,
    has_geolocation:       'geolocation' in navigator,
    has_webrtc:            'RTCPeerConnection' in window,
    has_webgl:             webgl1,
    has_webgl2:            webgl2,
    has_speech_synthesis:  'speechSynthesis' in window,
    has_payment_request:   'PaymentRequest' in window,
    has_share:             'share' in navigator,
    has_clipboard:         'clipboard' in navigator,
    has_bluetooth:         'bluetooth' in navigator,
    has_usb:               'usb' in navigator,
    has_serial:            'serial' in navigator,
    has_wakelock:          'wakeLock' in navigator,
    has_battery:           'getBattery' in navigator,
    has_vibration:         'vibrate' in navigator,
  };
}

// ============================================================================
//   SECTION R: PAGE INFO
// ============================================================================
function detectTrafficSource(ref) {
  if (!ref) return 'direct';
  const r = ref.toLowerCase();
  if (r.indexOf('google.com') !== -1)         return 'organic_google';
  if (r.indexOf('bing.com') !== -1)           return 'organic_bing';
  if (r.indexOf('duckduckgo.com') !== -1)     return 'organic_duckduckgo';
  if (r.indexOf('yahoo.') !== -1)             return 'organic_yahoo';
  if (r.indexOf('facebook.com') !== -1)       return 'social_facebook';
  if (r.indexOf('instagram.com') !== -1)      return 'social_instagram';
  if (r.indexOf('twitter.com') !== -1 || r.indexOf('x.com') !== -1) return 'social_twitter';
  if (r.indexOf('linkedin.com') !== -1)       return 'social_linkedin';
  if (r.indexOf('youtube.com') !== -1)        return 'social_youtube';
  if (r.indexOf('reddit.com') !== -1)         return 'social_reddit';
  if (r.indexOf('pinterest.com') !== -1)      return 'social_pinterest';
  if (r.indexOf('tiktok.com') !== -1)         return 'social_tiktok';
  if (r.indexOf('chat.openai.com') !== -1 || r.indexOf('claude.ai') !== -1 || r.indexOf('gemini.google.com') !== -1 || r.indexOf('perplexity.ai') !== -1) return 'ai_referral';
  return 'referral';
}

function getPageInfo() {
  const params = new URLSearchParams(window.location.search);
  return {
    page_url:              window.location.href,
    page_protocol:         window.location.protocol,
    page_host:             window.location.host,
    page_hostname:         window.location.hostname,
    page_port:             window.location.port,
    page_pathname:         window.location.pathname,
    page_search:           window.location.search,
    page_hash:             window.location.hash,
    page_origin:           window.location.origin,
    page_title:            document.title,
    page_referrer:         document.referrer,
    referrer:              document.referrer,
    page_charset:          document.characterSet,
    page_visibility_state: document.visibilityState,
    page_has_focus:        document.hasFocus(),
    landing_page:          window.location.href,
    utm_source:            params.get('utm_source'),
    utm_medium:            params.get('utm_medium'),
    utm_campaign:          params.get('utm_campaign'),
    utm_term:              params.get('utm_term'),
    utm_content:           params.get('utm_content'),
    fbclid:                params.get('fbclid'),
    gclid:                 params.get('gclid'),
    msclkid:               params.get('msclkid'),
    ttclid:                params.get('ttclid'),
    traffic_source:        detectTrafficSource(document.referrer),
  };
}

// ============================================================================
//   SECTION S: INCOGNITO
// ============================================================================
async function detectIncognito() {
  if ('storage' in navigator && 'estimate' in navigator.storage) {
    try { const e = await navigator.storage.estimate(); if ((e.quota || 0) < 120000000) return true; } catch (e) {}
  }
  return new Promise(resolve => {
    if (window.indexedDB && window.indexedDB.open) {
      try {
        const db = indexedDB.open('test_inco_' + Date.now());
        db.onerror   = () => resolve(true);
        db.onsuccess = () => resolve(false);
      } catch (e) { resolve(true); }
    } else resolve(false);
  });
}

// ============================================================================
//   SECTION T: BEHAVIOR
// ============================================================================
const behavior = {
  mouse_movements: [], mouse_clicks: [], scroll_events: [], key_events: [],
  tab_switches: 0, page_visible_time: 0, page_hidden_time: 0,
  session_start: Date.now(), max_scroll_depth: 0, total_scroll_distance: 0, last_scroll_y: 0,
};
let lastMouseLog = 0;

document.addEventListener('mousemove', e => {
  if (Date.now() - lastMouseLog < 200) return;
  lastMouseLog = Date.now();
  behavior.mouse_movements.push({ x: e.clientX, y: e.clientY, t: Date.now() - behavior.session_start });
  if (behavior.mouse_movements.length > 100) behavior.mouse_movements.shift();
}, { passive: true });

document.addEventListener('click', e => {
  if (behavior.mouse_clicks.length < 100) {
    behavior.mouse_clicks.push({
      x: e.clientX, y: e.clientY,
      target: (e.target && e.target.tagName) ? e.target.tagName : null,
      target_id: (e.target && e.target.id) ? e.target.id : null,
      target_class: (e.target && typeof e.target.className === 'string') ? e.target.className : null,
      t: Date.now() - behavior.session_start,
    });
  }
}, { passive: true });

window.addEventListener('scroll', () => {
  const sy = window.scrollY || window.pageYOffset || 0;
  const dh = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
  const wh = window.innerHeight || document.documentElement.clientHeight;
  const max = Math.max(1, dh - wh);
  const pct = Math.min(100, Math.max(0, Math.round((sy / max) * 100)));
  if (pct > behavior.max_scroll_depth) behavior.max_scroll_depth = pct;
  behavior.total_scroll_distance += Math.abs(sy - behavior.last_scroll_y);
  behavior.last_scroll_y = sy;
  if (behavior.scroll_events.length < 50) {
    behavior.scroll_events.push({ y: sy, percent: pct, t: Date.now() - behavior.session_start });
  }
}, { passive: true });

let visStart = Date.now();
document.addEventListener('visibilitychange', () => {
  const now = Date.now(); const elapsed = now - visStart;
  if (document.visibilityState === 'visible') { behavior.page_hidden_time += elapsed; behavior.tab_switches++; }
  else behavior.page_visible_time += elapsed;
  visStart = now;
});

document.addEventListener('keydown', e => {
  if (behavior.key_events.length < 50) {
    behavior.key_events.push({
      key_code: e.keyCode, ctrl: e.ctrlKey, shift: e.shiftKey, alt: e.altKey, meta: e.metaKey,
      t: Date.now() - behavior.session_start,
    });
  }
});

function getBehaviorSummary() {
  return {
    session_duration:       Math.floor((Date.now() - behavior.session_start) / 1000),
    page_visible_time:      Math.floor(behavior.page_visible_time / 1000),
    page_hidden_time:       Math.floor(behavior.page_hidden_time / 1000),
    mouse_movements_count:  behavior.mouse_movements.length,
    mouse_movements:        behavior.mouse_movements.length,
    mouse_clicks_count:     behavior.mouse_clicks.length,
    clicks_count:           behavior.mouse_clicks.length,
    scroll_events_count:    behavior.scroll_events.length,
    key_events_count:       behavior.key_events.length,
    keystrokes_count:       behavior.key_events.length,
    tab_switches:           behavior.tab_switches,
    max_scroll_depth:       behavior.max_scroll_depth,
    scroll_depth_max:       behavior.max_scroll_depth,
    total_scroll_distance:  behavior.total_scroll_distance,
    behavior_full_data: {
      mouse_movements_sample: behavior.mouse_movements.slice(-20),
      mouse_clicks:           behavior.mouse_clicks,
      scroll_events:          behavior.scroll_events,
      key_events:             behavior.key_events,
    },
  };
}

// ============================================================================
//   MASTER COLLECT & SEND
// ============================================================================
async function collectAll() {
  const [audio, media, rtc, perms, inco, hw, storage] = await Promise.all([
    getAudioFingerprint(), getMediaDevicesInfo(), getWebRTCIPs(),
    getPermissionsInfo(), detectIncognito(), getHardwareInfo(), getStorageEstimate(),
  ]);
  return Object.assign(
    {
      visitor_id:    getVisitorId(),
      session_id:    getSessionId(),
      timestamp:     Date.now(),
      timestamp_iso: new Date().toISOString(),
    },
    getBrowserInfo(),
    getScreenInfo(),
    hw,
    getNetworkInfo(),
    getTimezoneInfo(),
    getWebGLInfo(),
    getCanvasFingerprint(),
    audio,
    detectFonts(),
    getPluginsInfo(),
    getStorageInfo(),
    storage || {},
    perms,
    getSpeechVoices(),
    media,
    rtc,
    getPageInfo(),
    {
      is_incognito:    inco,
      codec_support:   getCodecSupport(),
      feature_support: getFeatureSupport(),
    },
    getBehaviorSummary()
  );
}

function send(data, useBeacon) {
  try {
    const body = JSON.stringify(data);
    if (useBeacon && navigator.sendBeacon) {
      const blob = new Blob([body], { type: 'application/json' });
      if (navigator.sendBeacon('/api/log.php', blob)) return;
    }
    fetch('/api/log.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body,
      keepalive: true,
      credentials: 'omit',
    }).catch(() => {});
  } catch (e) { /* silent */ }
}

async function fullSend(isFinal) {
  try {
    const data = await collectAll();
    data.is_final_beacon = !!isFinal;
    send(data, !!isFinal);
  } catch (e) { /* silent */ }
}

// schedule: initial after 3s, heartbeat every 30s, final beacon on unload
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setTimeout(() => fullSend(false), 3000);
} else {
  window.addEventListener('DOMContentLoaded', () => setTimeout(() => fullSend(false), 3000));
}
setInterval(() => fullSend(false), 30000);
window.addEventListener('beforeunload', () => fullSend(true));
window.addEventListener('pagehide',     () => fullSend(true));

})();
