document.addEventListener('DOMContentLoaded', function () {
  var panel = document.getElementById('agnfe-panel');
  if (!panel) return;
  var notice = panel.querySelector('.agnfe-message');
  var replace = document.getElementById('agnfe-replace');

  function updateFileSelection(input) {
    var filename = panel.querySelector('[data-agnfe-filename="' + input.id + '"]');
    if (filename) filename.textContent = input.files.length ? input.files[0].name : 'Nenhum arquivo selecionado';
    var upload = panel.querySelector('[data-agnfe-upload="' + input.id.replace('agnfe-', '') + '"]');
    if (upload) upload.disabled = !input.files.length;
    if (replace) {
      replace.disabled = !document.getElementById('agnfe-replace-xml').files.length ||
        !document.getElementById('agnfe-replace-pdf').files.length;
    }
  }

  panel.querySelectorAll('.agnfe-file-input').forEach(function (input) {
    input.addEventListener('change', function () { updateFileSelection(input); });
  });

  function submit(action, data, button) {
    if (button) button.disabled = true;
    data.append('ajax', '1');
    data.append('action', action);
    data.append('id_order', panel.dataset.idOrder);
    fetch(panel.dataset.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data})
      .then(function (response) { return response.json(); })
      .then(function (result) {
        notice.className = 'alert agnfe-message ' + (result.success ? 'alert-success' : 'alert-danger');
        notice.textContent = result.message;
        notice.style.display = '';
        if (result.success) window.location.reload();
        else if (button) button.disabled = false;
      })
      .catch(function () {
        notice.className = 'alert agnfe-message alert-danger';
        notice.textContent = 'Erro de comunicação. Recarregue a página para conferir o estado dos arquivos.';
        notice.style.display = '';
        if (button) button.disabled = false;
      });
  }
  panel.querySelectorAll('[data-agnfe-upload]').forEach(function (button) {
    button.addEventListener('click', function () {
      var type = button.dataset.agnfeUpload;
      var input = document.getElementById('agnfe-' + type);
      if (!input.files.length) return;
      var data = new FormData();
      data.append('file_type', type);
      data.append('document', input.files[0]);
      submit('Upload', data, button);
    });
  });
  if (replace) replace.addEventListener('click', function () {
    var xml = document.getElementById('agnfe-replace-xml');
    var pdf = document.getElementById('agnfe-replace-pdf');
    if (!xml.files.length || !pdf.files.length) return;
    var data = new FormData();
    data.append('xml', xml.files[0]);
    data.append('pdf', pdf.files[0]);
    submit('Replace', data, replace);
  });
  var retry = document.getElementById('agnfe-retry-notice');
  if (retry) retry.addEventListener('click', function () { submit('RetryNotice', new FormData(), retry); });
});
