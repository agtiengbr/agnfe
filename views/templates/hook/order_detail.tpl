<section class="card p-4 mb-4" aria-label="Nota fiscal">
  <h3>Nota fiscal disponível</h3>
  {if $agnfe_number}<p>NF-e nº {$agnfe_number|escape:'html':'UTF-8'}</p>{/if}
  <p><a class="btn btn-primary" href="{$agnfe_download_url|escape:'html':'UTF-8'}">Baixar DANFE (PDF)</a></p>
</section>
