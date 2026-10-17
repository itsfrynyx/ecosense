(function(){
  'use strict';
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));
  
  // Modals
  qsa('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-modal-open');
      const m = document.getElementById(id);
      if(m) m.hidden = false;
    });
  });
  qsa('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const m = btn.closest('.modal');
      if(m) m.hidden = true;
    });
  });
  qsa('.modal').forEach(m => {
    m.addEventListener('click', e => { if(e.target === m) m.hidden = true; });
  });
  
  // Edit device
  qsa('[data-edit-device]').forEach(btn => {
    btn.addEventListener('click', () => {
      try {
        const data = JSON.parse(btn.getAttribute('data-edit-device'));
        document.getElementById('edit_device_id').value = data.id;
        document.getElementById('edit_device_uid').value = data.device_uid || '';
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('edit_location').value = data.location || '';
        document.getElementById('edit_firmware').value = data.firmware || '';
        document.getElementById('edit_api_key').value = '';
        const m = document.getElementById('modalDeviceEdit');
        if(m) m.hidden = false;
      } catch(e){}
    });
  });
  
  // Rotate
  qsa('[data-edit-rule]').forEach(btn => {
    btn.addEventListener('click', () => {
      try {
        const rule = JSON.parse(btn.getAttribute('data-edit-rule'));
        const fields = {rule_id: 'id', device_id: 'device_id', metric: 'metric', operator: 'operator', threshold: 'threshold', severity: 'severity'};
        Object.entries(fields).forEach(([field, key]) => {
          document.querySelector('#modalRuleEdit [name="' + field + '"]').value = rule[key] ?? 0;
        });
        document.querySelector('#modalRuleEdit [name="enabled"]').checked = Number(rule.enabled) === 1;
        const allDevices = document.querySelector('#modalRuleEdit [name="device_all"]');
        if (allDevices) allDevices.checked = rule.device_id == null;
        document.getElementById('modalRuleEdit').hidden = false;
      } catch (error) {
        if (window.EcoToast) window.EcoToast('Could not open the alert rule', 'error');
      }
    });
  });

  qsa('[data-rotate-device]').forEach(btn => {
    btn.addEventListener('click', () => {
      if(confirm('Rotate API key? This will invalidate the old key.')) {
        document.getElementById('rotate_device_id').value = btn.getAttribute('data-rotate-device');
        document.getElementById('rotateForm').submit();
      }
    });
  });
  
  // Snippet
  qsa('[data-copy-snippet]').forEach(btn => {
    btn.addEventListener('click', () => {
      try {
        const d = JSON.parse(btn.getAttribute('data-copy-snippet'));
        const base = window.ECOSENSE_BASE || '/';
        const url = base + 'api/ingest.php';
        const curl = `curl -X POST "${url}" -H "X-Device-Key: ${d.api_key}" -H "Content-Type: application/json" -d '{"device_uid":"${d.device_uid}","temperature_c":28.4,"humidity_pct":65,"rain_detected":0}'`;
        document.getElementById('snippetUrl').textContent = url;
        document.getElementById('snippetApiKey').value = d.api_key;
        document.getElementById('snippetCurl').textContent = curl;
        const m = document.getElementById('modalSnippet');
        if(m) m.hidden = false;
      } catch(e){}
    });
  });
  const copyCurlBtn = qs('[data-copy-curl]');
  if(copyCurlBtn) {
    copyCurlBtn.addEventListener('click', () => {
      const txt = document.getElementById('snippetCurl')?.textContent || '';
      if(navigator.clipboard) navigator.clipboard.writeText(txt);
    });
  }
  
  // Simulate
  const simBtn = qs('[data-simulate]');
  if(simBtn) {
    simBtn.addEventListener('click', async () => {
      const uid = simBtn.getAttribute('data-simulate') || '';
      simBtn.disabled = true;
      try {
        const res = await fetch((window.ECOSENSE_BASE || '') + 'api/simulate.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.ECOSENSE_CSRF || ''
          },
          body: JSON.stringify(uid ? {device_uid: uid} : {})
        });
        const j = await res.json();
        if(j.ok) location.reload();
        else if(window.EcoToast) window.EcoToast(j.error || 'Could not send a test reading', 'error');
      } catch(e){
        if(window.EcoToast) window.EcoToast('Network error while sending the test reading', 'error');
      } finally {
        simBtn.disabled = false;
      }
    });
  }
})();
