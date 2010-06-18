/*
 * This file contains JavaScript functions related to the dataquery SQL check wizard
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */

Ext.onReady(function(){
    // basic tabs 1, built from existing content
    var tabs = new Ext.TabPanel({
        renderTo: 'tx_dataquery_wizardContainer',
        width: 450,
        activeTab: 0,
        frame: true,
        defaults: {autoHeight: true},
        items: [
            {contentEl: 'queryDebug', title: TX_DATAQUERY.labels.debugTab},
            {contentEl: 'queryPreview', title: TX_DATAQUERY.labels.previewTab}
        ]
    });
});
