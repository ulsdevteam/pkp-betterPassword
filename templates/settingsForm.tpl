{**
 * plugins/generic/betterPassword/settingsForm.tpl
 *
 * Copyright (c) 2021 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * Better Password plugin settings
 *
 *}
<script>
    $(function() {ldelim}
        // Attach the upload form handler.
        $('#betterPasswordSettingsForm').pkpHandler(
            '$.pkp.controllers.form.FileUploadFormHandler',
            {ldelim}
                $uploader: $('#plupload'),
                uploaderOptions: {ldelim}
                    uploadUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.betterpassword.handler.BlocklistHandler" op="uploadBlocklist" category="generic" plugin=$pluginName escape=false},
                    baseUrl: {$baseUrl|json_encode}
                {rdelim}
            {rdelim});
       {rdelim});

    {literal}
        //redefine modal handler function to update html with fresh list of blocklists from plugin delete handler
        $(function() {
            $.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.remoteResponse= function(ajaxOptions, jsonData) {
                var processedJsonData = this.parent('remoteResponse', ajaxOptions, jsonData);
                if (jsonData.content){
                    $('#blocklistFilesSection').html(jsonData.content);
                }
                if (processedJsonData !==false){
                    this.modalClose(ajaxOptions);
                }
                return false;
            };
        });
    {/literal}
</script>

<form class="pkp_form" id="betterPasswordSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="betterPasswordSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.betterPassword.manager.settings.description"}</div>

	{fbvFormArea id="betterPasswordSettingsFormOptions" title="admin.siteSettings"}
		{fbvFormSection for="minPasswordLength"}
			{fbvElement type="text" id="minPasswordLength" label="admin.settings.minPasswordLength" value="$minPasswordLength"}
		{/fbvFormSection}

		{if $betterPasswordBlocklistFiles}

		{fbvFormSection title="plugins.generic.betterPassword.manager.settings.betterPasswordExistingBlocklist"}
                    {assign var=templatePath value=$plugin->getTemplateResource('blocklistFilesList.tpl')}
                    {include file="$templatePath"}
		{/fbvFormSection}
		{/if}
		{fbvFormSection title="plugins.generic.betterPassword.manager.settings.betterPasswordBlocklist"}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="betterPasswordSettingsFormOptions" title="plugins.generic.betterPassword.manager.settings.betterPasswordCheckTitle"}
		{fbvFormSection list="true" id="betterPasswordCheckboxList"}
			{foreach from=$betterPasswordCheckboxes key="betterPasswordSetting" item="betterPasswordSettingValue"}
				{fbvElement type="checkbox" id=$betterPasswordSetting label="plugins.generic.betterPassword.manager.settings."|cat:$betterPasswordSetting checked=$betterPasswordSettingValue|compare:true}
			{/foreach}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="betterPasswordSettingsFormLocking" title="plugins.generic.betterPassword.manager.settings.betterPasswordLockTitle"}
		{foreach from=$betterPasswordLocking key="betterPasswordSetting" item="betterPasswordSettingValue"}
			{fbvFormSection for="$betterPasswordSetting" description="plugins.generic.betterPassword.manager.settings."|cat:$betterPasswordSetting id=$betterPasswordSetting|cat:"Section"}
				{fbvElement type="text" id=$betterPasswordSetting value=$betterPasswordSettingValue inline=true size=$fbvStyles.size.MEDIUM}
			{/fbvFormSection}
		{/foreach}
	{/fbvFormArea}

	{fbvFormArea id="betterPasswordSettingsFormInvalidation" title="plugins.generic.betterPassword.manager.settings.betterPasswordInvalidationTitle"}
		{foreach from=$betterPasswordInvalidation key="betterPasswordSetting" item="betterPasswordSettingValue"}
			{fbvFormSection for="$betterPasswordSetting" description="plugins.generic.betterPassword.manager.settings."|cat:$betterPasswordSetting id=$betterPasswordSetting|cat:"Section"}
				{fbvElement type="text" id=$betterPasswordSetting value=$betterPasswordSettingValue inline=true size=$fbvStyles.size.MEDIUM}
			{/fbvFormSection}
		{/foreach}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
