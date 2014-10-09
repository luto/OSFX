/*
 * This was taken and modified from the "shownotes" plugin by Simon Waldherr.
 *
 * http://wordpress.org/plugins/shownotes/
 */

 (function($) {
  $( document ).ready( function() {
      $(".osfx-tab").on( 'click', function () {
        $(".osfx-tab").removeClass("nav-tab-active");
        $(this).addClass("nav-tab-active");
        $(".osfx-tab-container").hide();
        $( "#osfx_" + $(this).data("container") ).show();
      } );
      $("#osfx-chapters-button").on( 'click', function () {
        getParserResponse('osfx-chapters');
      } );
      $("#osfx-validate-button").on( 'click', function () {
        getParserResponse('osfx-validate');
      } );
      $("#import_into_publisher_button").on( 'click', function () {
        $("#_podlove_meta_chapters").text($("#osfx_chapters_textarea").text());
        $("#_podlove_meta_chapters").autogrow();
      } );
  } );

  function getParserResponse( action ) {
    var source = editor.getValue();

    var data = {
      action: action,
      source: source
    };

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: data,
      dataType: 'json',
      success: function(result) {
        switch( action ) {
          case "osfx-chapters":
            $("#osfx_chapters_paragraph").html("<textarea rows=\"10\" class=\"large-text code\" id=\"osfx_chapters_textarea\" readonly>"+result+"</textarea>");
            $("#osfx_chapters_textarea").select();
          break;
          case "osfx-validate":
          console.log(result);
            if ( result == "" ) {
              $("#osfx_validation_table").hide();
               $("#osfx_validation_status").html("<span class='isValid'>Your Shownotes markup seems to be valid.</span>");
            } else {
              $("#osfx_validation_status").html("<span class='isInValid'>Your Shownote markup contains errors!</span>");
              $("#osfx_validation_table").show();
              $("#osfx_validation_table_body").html(result);
            }

            
          break;
        }
      }
    });
  }
 }(jQuery));


function getPadList(select, podcastname) {
  "use strict";
  var requrl,
    padslist,
    returnstring = '',
    i;

  if (podcastname.trim() === "*") {
    requrl = 'http://cdn.simon.waldherr.eu/projects/showpad-api/getList/';
  } else {
    requrl = 'http://cdn.simon.waldherr.eu/projects/showpad-api/getList/?search=' + podcastname.trim();
  }

  majaX({url: requrl, type: 'json'}, function (resp) {
    padslist = resp;
    padslist.sort(function(a, b) {
      a = a.docname.toLowerCase();
      b = b.docname.toLowerCase();
      if (a < b) {
        return 1;
      }
      if (a > b) {
        return -1;
      }
      return 0;
    });
    for (i = 0; i < padslist.length; i += 1) {
      if (shownotesname === padslist[i].docname) {
        returnstring += '<option selected>' + padslist[i].docname + '</option>';
      } else {
        returnstring += '<option>' + padslist[i].docname + '</option>';
      }
    }
    select.innerHTML = returnstring;
  });
}

function importShownotes(textarea, importid, baseurl) {
  "use strict";
  var requrl;
  requrl = baseurl.replace("$$$", importid);
  majaX({url: requrl}, function (resp) {
    editor.setValue( resp.trim() );
  });
}
