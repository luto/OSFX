/*
 * This was taken and modified from the "shownotes" plugin by Simon Waldherr.
 *
 * http://wordpress.org/plugins/shownotes/
 */

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
