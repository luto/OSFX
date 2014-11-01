<?php

namespace OSFX\Settings;

class Settings {
	function admin_menu() {
		add_options_page(
				'OSF X Options',
				'OSF X',
				'manage_options',
				'osfx',
				array( 'OSFX\Settings\Settings', 'osfx_settings_page')
			);
	}

	function register_settings() {
		register_setting( 'osfx_options', 'osfx_search' );
		register_setting( 'osfx_options', 'osfx_template' );
		register_setting( 'osfx_options', 'osfx_showpad' );
		register_setting( 'osfx_options', 'osfx_affiliations' );
	}

	function osfx_settings_page() {
		?>
		<div class="wrap">
		<h2>Shownotes Options</h2>
		<form method="post" action="options.php" class="osfx-options">
			<?php settings_fields( 'osfx_options' ); ?>
			<?php do_settings_sections( 'osfx_options' ); ?>
			<h3>General</h3>
			<table class="form-table">
		        <tr valign="top">
		        	<th scope="row">
		        		Search
		        	</th>
		       		<td>
		       			<input type="checkbox" id="osfx_search" name="osfx_search" <?php echo ( get_option('osfx_search') == 'on' ? 'checked' : '' ) ?>/>
		       			<label for="osfx_search">Include Shownotes in WordPress search</label>
		       		</td>
		        </tr>
			</table>
			<h3>Template &amp; Styles</h3>
			Adjust the style and the way how shownotes are presented in your Podcast.
			<table class="form-table">
		        <tr valign="top">
		        	<th scope="row">
		        		Template
		        	</th>
		       		<td>
		       			<table class="podlove_alternating" border="0" cellspacing="0">
		       				<thead>
		       					<tr>
		       						<th>Template</th>
		       						<th>Delete</th>
		       					</tr>
		       				</thead>
		       				<tbody id="templates_table_body"></tbody>
		       			</table>
		       			<input type="button" class="button" id="add_template" value="+" />
		       		</td>
		        </tr>
			</table>
			<script type="text/template" id="template_line_template">
			<tr> 
				<td class="osfx_template_options_row">
					<span class="osfx_template_triangle" id="osfx_template_triangle_{{counter}}">►</span>
					<h4 class="osfx_template_id">{{name}}</h4>
					<div class="osfx_template_source_wrapper">
						<input type="hidden" name="osfx_template[{{counter}}][editable]" id="osfx_template_{{counter}}_editable" value="{{editable}}" />
						<input type="text" name="osfx_template[{{counter}}][id]" value="{{name}}" placeholder="Template ID" class="osfx_template_id" {{editable}} />
						<label for="">Description to identify the template in the shortcode</label>
						<div id="ace-shownotes-{{counter}}" class="ace-shownotes"></div>
						<textarea cols="80" rows="10" id="osfx_template_{{counter}}_source" name="osfx_template[{{counter}}][source]" class="osfx_template_source">{{source}}</textarea>
						<label for="">Templates support HTML and Twig. Read the Template Guide to get started.</label>
					</div>
				</td>
				<td class="osfx_template_action_row">
					<span class="delete_template"></span>
				</td>
			</tr>
			</script>
			<script type="text/javascript">
				var template_counter = 0;
				var templates = <?php echo json_encode(get_option('osfx_template')); ?>;

				(function($) {
				  $( document ).ready( function() {
				  	var editor = new Object();
				  	var textarea = new Object();

				  	$.each( templates, function ( id ) {
				  		add_template(id);
				  		$(".delete_affiliate_program").on( 'click', function() {
				  			$(this).closest("tr").remove();	
				  		} );
				  		$("#osfx_template_triangle_" + id).on( 'click', function () {
				  		  if ( $(this).text() == '►' ) {
				  		    $(this).text('▼');
				  		  } else {
				  		    $(this).text('►');
				  		  }
				  		  $(this).parent().find('.osfx_template_source_wrapper').toggle();
				  		} );
				  	});

				  	function add_template( id ) {
				  		var source = $("#template_line_template").html();
				  		source = source.replace( /\{\{source\}\}/g, templates[id].source );
				  		source = source.replace( /\{\{name\}\}/g, templates[id].id );
				  		source = source.replace( /\{\{counter\}\}/g, template_counter );
				  		source = source.replace( /\{\{editable\}\}/g, templates[id].editable );

				  		$("#templates_table_body").append( source );
				  		row = $("#templates_table_body tr:last");

				  		$(".delete_template").on( 'click', function() {
				  			$(this).closest("tr").remove();	
				  		} );

				  		editor[template_counter] = ace.edit("ace-shownotes-" + template_counter);
				  		$("#ace-shownotes-" + template_counter).data("test", template_counter);
				  		textarea[template_counter] = jQuery("#osfx_template_" + template_counter + "_source");
				  		textarea[template_counter].hide();
				  		if ( jQuery("#osfx_template_" + template_counter + "_editable").val() == 'readonly' )
				  			editor[template_counter].setReadOnly(true);
				  		editor[template_counter].getSession().setUseWrapMode(true);
				  		editor[template_counter].setTheme("ace/theme/textmate");
				  		editor[template_counter].getSession().setValue(textarea[template_counter].val());
				  		editor[template_counter].getSession().setMode("ace/mode/twig");
				  		$("#ace-shownotes-" + template_counter).on('keyup', function() {
				  			textarea[$(this).data("test")].val(editor[$(this).data("test")].getSession().getValue()); // Must keep its counter in mind!
				  		});
				  		$(".osfx_template_id").on( 'keyup', function() {
				  		  $(this).parent().parent().find("h4.osfx_template_id").text($(this).val());
				  		} );

				  		$("#ace-shownotes-" + template_counter).css( 'position', 'relative' );
				  		template_counter++;
				  	}

				  	$("#add_template").on( 'click', function () {
				  		var source = $("#template_line_template").html();
				  		source = source.replace( /\{\{source\}\}/g, "" );
				  		source = source.replace( /\{\{name\}\}/g, "" );
				  		source = source.replace( /\{\{counter\}\}/g, template_counter );
				  		source = source.replace( /\{\{editable\}\}/g, "" );
				  		$("#templates_table_body").append( source );
				  		row = $("#templates_table_body tr:last");

				  		$("#osfx_template_triangle_" + template_counter).on( 'click', function () {
				  		  if ( $(this).text() == '►' ) {
				  		    $(this).text('▼');
				  		  } else {
				  		    $(this).text('►');
				  		  }
				  		  $(this).parent().find('.osfx_template_source_wrapper').toggle();
				  		} );

				  		row.find("#osfx_template_triangle_" + template_counter).click();
				  		$(".delete_template").on( 'click', function() {
				  			$(this).closest("tr").remove();	
				  		} );

				  		editor[template_counter] = ace.edit("ace-shownotes-" + template_counter);
				  		$("#ace-shownotes-" + template_counter).data("test", template_counter);
				  		textarea[template_counter] = jQuery("#osfx_template_" + template_counter + "_source");
				  		textarea[template_counter].hide();
				  		editor[template_counter].getSession().setUseWrapMode(true);
				  		editor[template_counter].setTheme("ace/theme/textmate");
				  		editor[template_counter].getSession().setValue(textarea[template_counter].val());
				  		editor[template_counter].getSession().setMode("ace/mode/twig");
				  		$("#ace-shownotes-" + template_counter).on('keyup', function() {
				  			textarea[$(this).data("test")].val(editor[$(this).data("test")].getSession().getValue()); // Must keep its counter in mind!
				  		});
				  		$(".osfx_template_id").on( 'keyup', function() {
				  		  $(this).parent().parent().find("h4.osfx_template_id").text($(this).val());
				  		} );

				  		$("#ace-shownotes-" + template_counter).css( 'position', 'relative' );
				  		template_counter++;
				  	});
				  } );
				}(jQuery));
			</script>
			<h3>Affiliation</h3>
			Configure your affiliate programs.
			<table class="form-table">        
		        <tr valign="top">
			        <th scope="row">
			        	Affiliations
			        </th>
			        <td>
			        	<table class="podlove_alternating" border="0" cellspacing="0">
			        		<thead>
			        			<tr>
			        				<th>Affiliate Program</th>
			        				<th>Affiliate ID</th>
			        				<th>Delete</th>
			        			</tr>
			        		</thead>
			        		<tbody id="affiliate_program_table_body"></tbody>
			        	</table>
			        	<input type="button" class="button" id="add_affiliate_program" value="+" />
			        </td>
		        </tr>
		    </table>
		    <?php require('affiliate_programs.php'); ?>
		    <script type="text/template" id="affiliate_line_template">
		    	<tr>
		    		<td>
		    			<select class="chosen affiliate_programs" name="osfx_affiliations[{{counter}}][affiliate_program]">
		    				<option value="">&nbsp;</option>
		    			</select>
		    		</td>
		    		<td>
		    			<input type="text" placeholder="Affiliate ID" value="{{affiliate-id}}" name="osfx_affiliations[{{counter}}][affiliate_id]" />
		    		</td>
		    		<td>
		    			<span class="delete_affiliate_program"></span>
		    		</td>
		    	</tr>
		    </script>
		    <?php 
		    	$existing_affiliations = get_option('osfx_affiliations');
		    	$for_js_existing_affiliation = array();
		    	if ( ! empty($existing_affiliations) )
		    		foreach ($existing_affiliations as $existing_affiliation) {
		    			$for_js_existing_affiliation[$existing_affiliation['affiliate_program']] = $existing_affiliation['affiliate_id'];
		    		}
		    ?>
		    <script type="text/javascript">
		    	var counter = 0;
		    	var affiliate_programs = <?php echo json_encode($affiliate_programs); ?>;
		    	var existing_affiliation = <?php echo json_encode($for_js_existing_affiliation); ?>;

		    	(function($) {
		    	  $( document ).ready( function() {
		    	  	$("#add_affiliate_program").on( 'click', function () {
		    	  		var source = $("#affiliate_line_template").html();
		    	  		source = source.replace( /\{\{affiliate-id\}\}/g, "" );
		    	  		source = source.replace( /\{\{counter\}\}/g, counter );
		    	  		counter++;

		    	  		$("#affiliate_program_table_body").append( source );
		    	  		populate_dropdowns();
		    	  		$(".chosen").chosenImage();

		    	  		$(".delete_affiliate_program").on( 'click', function() {
		    	  			$(this).closest("tr").remove();	
		    	  		} );
		    	  	});

		    	  	$.each( existing_affiliation, function ( id ) {
		    	  		add_affiliation(id);
		    	  		$(".delete_affiliate_program").on( 'click', function() {
		    	  			$(this).closest("tr").remove();	
		    	  		} );
		    	  	});

		    	  	function add_affiliation( id ) {
		    	  		affiliation = affiliate_programs[id];
		    	  		source = $("#affiliate_line_template").html();
		    	  		// Fill in the provided information
		    	  		source = source.replace( /\{\{affiliate-id\}\}/g, existing_affiliation[id] );
		    	  		source = source.replace( /\{\{counter\}\}/g, counter );
		    	  		counter++;
		    	  		// Append new row
		    	  		$("#affiliate_program_table_body").append( source );
		    	  		
		    	  		row = $("#affiliate_program_table_body tr:last");
		    	  		populate_dropdowns();
		    	  		// Select the correct affiliate program
		    	  		row.find('select.affiliate_programs option[value="' + id + '"]').attr('selected',true);
		    	  		$(".chosen").chosenImage();
		    	  	}

		    	    function populate_dropdowns() {
		    	    	$.each( affiliate_programs, function ( id, affiliate_program ) {
		    	    		$(".affiliate_programs").append("<option value='" + id + "' data-img-src='<?php echo plugins_url() ?>/OSFX/img/" + affiliate_program.icon + "'>" + affiliate_program.title +"</option>");
		    	    		
		    	    	});
		    	    }

		    	    populate_dropdowns();
		    	  });
		    	}(jQuery));

		    </script>
			<h3>Import from ShowPad</h3>
			The plugin allows you to easily import Shownotes from Showpad.
			<table class="form-table">        
		        <tr valign="top">
			        <th scope="row">
			        	Podcast Name
			        </th>
			        <td>
			        	<input type="text" id="osfx_showpad" name="osfx_showpad" value="<?php echo get_option('osfx_showpad'); ?>" />
			        	<label for="osfx_showpad">enter the Podcastname in the Showpad</label>
			        </td>
		        </tr>
		    </table>
		    <?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	function admin_scripts_and_styles() {
		wp_register_script(
			'ace',
			plugins_url() . '/OSFX/lib/ace/ace.js',
			false
		);
		wp_enqueue_script('ace');

		wp_register_style(
			'osfx_settings_styles',
			plugins_url() . '/OSFX/osfx.css',
			false
		);
		wp_enqueue_style('osfx_settings_styles');

		wp_register_script(
			'majax',
			plugins_url() . '/OSFX/lib/majaX/majax.min.js',
			false
		);
		wp_enqueue_script('majax');

		wp_register_script(
			'osfx_js',
			plugins_url() . '/OSFX/lib/osfx.js',
			false
		);
		wp_enqueue_script('osfx_js');

		wp_register_script(
			'chosen',
			plugins_url() . '/OSFX/lib/chosen/chosen.jquery.min.js',
			false
		);
		wp_enqueue_script('chosen');

		wp_register_script(
			'chosen_image',
			plugins_url() . '/OSFX/lib/chosen/chosenImage.jquery.js',
			false
		);
		wp_enqueue_script('chosen_image');
	}
}
?>