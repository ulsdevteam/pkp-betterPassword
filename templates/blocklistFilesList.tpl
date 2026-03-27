{**
 * plugins/generic/betterPassword/blocklistFilesSection.tpl
 *
 * Copyright (c) 2025 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * Better Password plugin settings, list user-uploaded blocklist files
 *
 *}

 <p>
    <div id="blocklistFilesSection">
        {foreach from=$betterPasswordBlocklistFiles key="betterPasswordFile" item="betterPasswordSettingValue"}
        <p>
            {$betterPasswordFile} {include file="linkAction/linkAction.tpl" action=$betterPasswordSettingValue}
        </p>
        {/foreach}
    </div>
</p>
