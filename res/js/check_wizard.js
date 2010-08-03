/*
 * This file contains JavaScript functions related to the dataquery SQL check wizard
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @author		Fabien Udriot <fabien.udriot@ecodev.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */

Ext.onReady(function(){

		// Container that includes widgets for validating the query
	new Ext.Container({
        renderTo: 'tx_dataquery_wizardContainer',
		items: [
			{
				xtype: 'button',
				text: TX_DATAQUERY.labels.validateButton,
				style: {
					 marginBottom: '10px'
				},
				handler: function() {
					var textarea = Ext.get(TX_DATAQUERY.fieldId);

						// Basic request in Ext
					Ext.Ajax.request({
						url: 'ajax.php',
						method: 'post',
						params: {
							ajaxID: 'dataquery::validate',
							query: textarea.dom.value
						},
						success: function(result){
							Ext.get('t3-box-result').update(result.responseText);
						}
					});
				}
			},
			{
				xtype: 'box',
				id: 't3-box-result',
				html: ''
			}
		]
	});

	// For a possible further use
//    var tabs = new Ext.TabPanel({
//        renderTo: 'tx_dataquery_wizardContainer',
//        width: 450,
//        activeTab: 0,
//        frame: true,
//        defaults: {autoHeight: true},
//        items: [
//			{
//				title: TX_DATAQUERY.labels.debugTab,
//				items: [
//					{
//						xtype: 'button',
//						text: TX_DATAQUERY.labels.validateButton,
//						handler: function() {
//							console.log(123);
//						}
//					},
//					{
//						xtype: 'box',
//						html: 'result goes here'
//					}
//				]
//			},
//			{
//				title: TX_DATAQUERY.labels.previewTab,
//				items: [
//					{
//						xtype: 'button',
//						text: 'to be done next...'
//					}
//				]
//			}
//        ]
//    });
});
