/**
*   Toggle enabled fields for blogs.
*
*   @param  object  cbox    Checkbox
*   @param  string  id      Blog SID
*   @param  string  type    Type of element (draft, featured, etc)
*/
var BLOG_toggle = function(cbox, id, type) {
    oldval = cbox.checked ? 0 : 1;
     var dataS = {
        "action" : "toggle",
        "id": id,
        "type": type,
        "oldval": oldval,
    };
    data = $("form").serialize() + "&" + $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/blog/ajax.php",
        data: data,
        success: function(result) {
            // Set the ID of the updated checkbox
            spanid = result.type + '_' + result.id;
            chk = result.newval == 1 ? true : false;
            document.getElementById(spanid).checked = chk;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};
