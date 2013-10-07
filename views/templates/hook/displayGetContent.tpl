<h2>{l s='SellerMania' mod='sellermania'}</h2>

<form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
    <fieldset>
        <legend><img src="{$sellermania_module_path}logo.gif" alt="" title="" />{l s='SellerMania configuration' mod='sellermania'}</legend>
        <div class="margin-form" style="padding-left:15px">
            <p>{l s='You have two solutions to send your catalog to SellerMania' mod='sellermania'}</p><br>
            <p><b>1) {l s='Set a cron task' mod='sellermania'}</b></p>
            <p>{l s='Script path:' mod='sellermania'} {$script_path}/export.php -k {$sellermania_key}</p>
            <p>{l s='Generated files will be available at these urls:' mod='sellermania'}</p>
            <p>
            {foreach from=$languages_list item=language}
                <strong>{$language.iso_code|strtoupper} :</strong> {$module_web_path}export/export-{$language.iso_code|strtolower}-{$sellermania_key}.csv <br>
            {/foreach}
            </p>

            <br><p><b><u>{l s='OR' mod='sellermania'}</u></b></p><br>

            <p><b>2) {l s='Send these links to SellerMania' mod='sellermania'}</b></p>
            <p>
            {foreach from=$languages_list item=language}
                <strong>{$language.iso_code|strtoupper} :</strong> {$module_web_path}export.php?l={$language.iso_code|strtolower}&k={$sellermania_key} <br>
            {/foreach}
            </p>

        </div>
    </fieldset>
</form>