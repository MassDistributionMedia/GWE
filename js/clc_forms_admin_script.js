/**
* @desc CLC Forms
* @author Jeffrey Johnson - http://redshard.com
*/

jQuery(document).ready(function($){
	var ed = $("#form-editor");
	var ajaxurl = $("#ajax_url").val();
	
	var createForm = $("#dialog-create-form").dialog({
		autoOpen: false
	  , modal: true
	  , width: 350
	  , buttons: {
			"Cancel": function() {
				$(this).dialog('close');
			}
		}
	});
	
	$("a.add-new-h2").click(function(e){
		createForm.dialog('open');
		e.preventDefault();
	});
	
	$("#form-progress").progressbar({value:100});
	
	$("#btn-create-form").button().click(function(e){
		$(this).hide();
		$("#form-progress").show();
		
		var data = {
			action: 'clc_forms_edit'
		  , form_action: 'create'
		  , _ajax_nonce: $("#_wpnonce").val()
		};
		
		$.post(ajaxurl, data, function(data, status) {
			$("#form-progress").hide();
			
			$("#form-ajax-output").html(data);
			
			$("<span>").html("Edit the form, and then select \"Publish to CLC\" from the menu").appendTo("#form-ajax-output");
		});
	})
	
	var iEditForm = {
		init: function(){
			iEditForm.editor = ed;
			iEditForm.list = $("#the-list");
			iEditForm.label = ed.find("input[name=form_label]");
			iEditForm.visible = ed.find("input[name=form_visible]");
			iEditForm.id = 0;
			
			ed.find("a.cancel").click(function(e){
				iEditForm.revert();
				e.preventDefault();
			});
			ed.find("a.save").click(function(e){
				iEditForm.save();
				e.preventDefault();
			});
			
			
			iEditForm.list.on('click', "tr div.row-actions span.edit a", function(e){
				var form = $(this).closest("tr")
				  , id = form.attr('id').match(/^form-(.+)$/)[1];
				
				iEditForm.edit(form, id);
				e.preventDefault();
			});
		}
		, edit: function(row, id){
			iEditForm.target = row;
			iEditForm.id = id;
			iEditForm.action = 'edit';
			
			iEditForm.updateFrom(row);
			
			iEditForm.editor.insertAfter(row.hide()).show();
		}
		, updateFrom: function(row) {
			iEditForm.label.val(row.find("span.column-label").text());
			if ( row.find("span.column-visibility").text() == 'Visible' )
				iEditForm.visible.attr('checked','checked');
			else
				iEditForm.visible.removeAttr('checked');
		}
		, clear: function(){
			iEditForm.label.val('');
			iEditForm.visible.removeAttr('checked');
		}
		, revert: function(){
			iEditForm.editor.appendTo(iEditForm.list).hide();
			iEditForm.clear();
			if( iEditForm.target )
				iEditForm.target.show();
			iEditForm.id = 0;
			iEditForm.target = 0;
		}
		, save: function(){
			var data = {
				form_id : iEditForm.id
			  , form_label : iEditForm.label.val()
			  , form_visible : iEditForm.visible.get(0).checked
			  , form_action : iEditForm.action
			  , action : "clc_forms_edit"
			  , _ajax_nonce : iEditForm.editor.find("#_wpnonce").val()
			  , XDEBUG_SESSION_START : 'ECLIPSE_DBGP'
			};
			
			$.post(ajaxurl, data, function(data, status){
				var form = $(data);
				
				var oform = $("#"+form.attr('id'))
				
				if( oform.length > 0 ) {
					oform.html(form.html());
				}
				else {
					iEditForm.list.append(form);
				}
				
				iEditForm.revert();
			});
		}
		, create: function(){
			iEditForm.action = 'create';
			iEditForm.clear();
			iEditForm.editor.prependTo(iEditForm.list).show();
		}
	}
	
	iEditForm.init();
});