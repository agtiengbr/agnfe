<div class="tab-pane fade" id="agnfe-content" role="tabpanel" aria-labelledby="agnfe-tab">
  <div class="card mb-3 agnfe-card" id="agnfe-panel" data-id-order="{$agnfe_id_order|intval}" data-ajax-url="{$agnfe_ajax_url|escape:'html':'UTF-8'}">
    <div class="card-header agnfe-heading">
      <div>
        <span class="agnfe-eyebrow">Documentos do pedido</span>
        <h3>Nota fiscal</h3>
        <p>XML da NF-e e DANFE em PDF, cadastrados uma única vez.</p>
      </div>
      {if $agnfe_doc && $agnfe_doc.published}
        <span class="agnfe-state agnfe-state--ready"><span class="agnfe-state-dot"></span>Disponível ao cliente</span>
      {elseif $agnfe_doc && ($agnfe_doc.xml_path || $agnfe_doc.pdf_path)}
        <span class="agnfe-state agnfe-state--pending"><span class="agnfe-state-dot"></span>Aguardando documento</span>
      {else}
        <span class="agnfe-state agnfe-state--empty"><span class="agnfe-state-dot"></span>Aguardando arquivos</span>
      {/if}
    </div>
    <div class="card-body agnfe-body">
      <div class="alert agnfe-message" role="status" aria-live="polite" style="display:none"></div>
      {if $agnfe_doc && $agnfe_doc.published}
        <div class="agnfe-summary">
          <div class="agnfe-summary-details">
            <strong>NF-e nº {$agnfe_doc.nfe_number|escape:'html':'UTF-8'}</strong>
            <span>Chave: {$agnfe_doc.nfe_key|escape:'html':'UTF-8'}</span>
          </div>
          <div class="agnfe-document-links">
            <a class="btn btn-outline-secondary" href="{$agnfe_admin_xml_url|escape:'html':'UTF-8'}">Baixar XML</a>
            <a class="btn btn-outline-primary" href="{$agnfe_admin_pdf_url|escape:'html':'UTF-8'}">Baixar DANFE</a>
          </div>
        </div>
        <div class="agnfe-section-heading">
          <h4>Corrigir documentos</h4>
          <p>Selecione um novo XML e um novo PDF. A troca só acontece após validar os dois arquivos.</p>
        </div>
        <div class="agnfe-file-grid">
          <div class="agnfe-file-card">
            <label class="agnfe-file-title" for="agnfe-replace-xml">Novo XML da NF-e</label>
            <div class="agnfe-picker">
              <input type="file" id="agnfe-replace-xml" class="agnfe-file-input" accept=".xml,text/xml,application/xml">
              <label for="agnfe-replace-xml" class="agnfe-picker-button">Escolher XML</label>
              <span class="agnfe-file-name" data-agnfe-filename="agnfe-replace-xml">Nenhum arquivo selecionado</span>
            </div>
          </div>
          <div class="agnfe-file-card">
            <label class="agnfe-file-title" for="agnfe-replace-pdf">Novo DANFE em PDF</label>
            <div class="agnfe-picker">
              <input type="file" id="agnfe-replace-pdf" class="agnfe-file-input" accept=".pdf,application/pdf">
              <label for="agnfe-replace-pdf" class="agnfe-picker-button">Escolher PDF</label>
              <span class="agnfe-file-name" data-agnfe-filename="agnfe-replace-pdf">Nenhum arquivo selecionado</span>
            </div>
          </div>
        </div>
        <div class="agnfe-actions">
          <button type="button" id="agnfe-replace" class="btn btn-primary" disabled>Substituir nota fiscal</button>
          <span>O cliente não recebe outro aviso automaticamente.</span>
        </div>
        {if $agnfe_doc.notification_status == 1}
          <div class="agnfe-notice agnfe-notice--success">Aviso enviado por e-mail em {$agnfe_doc.notification_sent_at|escape:'html':'UTF-8'}.</div>
        {else}
          <div class="agnfe-notice agnfe-notice--warning">
            <span>O aviso por e-mail está pendente ou falhou.</span>
            <button type="button" id="agnfe-retry-notice" class="btn btn-outline-warning btn-sm">Tentar novamente</button>
          </div>
        {/if}
      {else}
        <p class="agnfe-intro">Anexe os dois arquivos para disponibilizar o DANFE ao cliente e registrar a tentativa de aviso por e-mail.</p>
        <div class="agnfe-file-grid">
          <section class="agnfe-file-card{if $agnfe_doc && $agnfe_doc.xml_path} agnfe-file-card--ready{/if}" aria-labelledby="agnfe-xml-title">
            <div class="agnfe-file-heading">
              <span class="agnfe-step-number">1</span>
              <div>
                <h4 id="agnfe-xml-title">XML da NF-e</h4>
                <p>Contém o número e a chave usados no envio.</p>
              </div>
              {if $agnfe_doc && $agnfe_doc.xml_path}<span class="agnfe-file-status">Anexado</span>{/if}
            </div>
            {if $agnfe_doc && $agnfe_doc.xml_path}<a class="agnfe-existing-file" href="{$agnfe_admin_xml_url|escape:'html':'UTF-8'}">Baixar XML anexado</a>{/if}
            <div class="agnfe-picker">
              <input type="file" id="agnfe-xml" class="agnfe-file-input" accept=".xml,text/xml,application/xml">
              <label for="agnfe-xml" class="agnfe-picker-button">Escolher XML</label>
              <span class="agnfe-file-name" data-agnfe-filename="agnfe-xml">Nenhum arquivo selecionado</span>
            </div>
            <button type="button" class="btn btn-outline-primary agnfe-upload-button" data-agnfe-upload="xml" disabled>{if $agnfe_doc && $agnfe_doc.xml_path}Trocar XML{else}Anexar XML{/if}</button>
          </section>
          <section class="agnfe-file-card{if $agnfe_doc && $agnfe_doc.pdf_path} agnfe-file-card--ready{/if}" aria-labelledby="agnfe-pdf-title">
            <div class="agnfe-file-heading">
              <span class="agnfe-step-number">2</span>
              <div>
                <h4 id="agnfe-pdf-title">DANFE em PDF</h4>
                <p>Arquivo que o cliente poderá baixar.</p>
              </div>
              {if $agnfe_doc && $agnfe_doc.pdf_path}<span class="agnfe-file-status">Anexado</span>{/if}
            </div>
            {if $agnfe_doc && $agnfe_doc.pdf_path}<a class="agnfe-existing-file" href="{$agnfe_admin_pdf_url|escape:'html':'UTF-8'}">Baixar DANFE anexado</a>{/if}
            <div class="agnfe-picker">
              <input type="file" id="agnfe-pdf" class="agnfe-file-input" accept=".pdf,application/pdf">
              <label for="agnfe-pdf" class="agnfe-picker-button">Escolher PDF</label>
              <span class="agnfe-file-name" data-agnfe-filename="agnfe-pdf">Nenhum arquivo selecionado</span>
            </div>
            <button type="button" class="btn btn-outline-primary agnfe-upload-button" data-agnfe-upload="pdf" disabled>{if $agnfe_doc && $agnfe_doc.pdf_path}Trocar PDF{else}Anexar PDF{/if}</button>
          </section>
        </div>
      {/if}
    </div>
  </div>
</div>
