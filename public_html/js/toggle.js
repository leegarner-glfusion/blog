/*  $Id: toggleEnabled.js 2 2009-12-30 04:11:52Z root $
 *  Updates database values as checkboxes are checked.
 */
var xmlHttp;
function BLOG_toggle(ckbox, sid, component, base_url)
{
  xmlHttp=BL_getXmlHttpObject();
  if (xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  // value is reversed since we send the oldvalue to ajax
  var oldval = ckbox.checked == true ? 0 : 1;
  var url=base_url + "/ajax.php?action=toggle";

  url=url+"&sid="+sid;
  url=url+"&component="+component;
  url=url+"&oldval="+oldval;
  //url=url+"&sid="+Math.random();
  xmlHttp.onreadystatechange=BL_stateChanged;
  xmlHttp.open("GET",url,true);
  xmlHttp.send(null);
}

function BL_stateChanged()
{
  var newstate;

  if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete")
  {
    xmlDoc=xmlHttp.responseXML;
    sid = xmlDoc.getElementsByTagName("sid")[0].childNodes[0].nodeValue;
    baseurl = xmlDoc.getElementsByTagName("baseurl")[0].childNodes[0].nodeValue;
    component = xmlDoc.getElementsByTagName("component")[0].childNodes[0].nodeValue;
    newval = xmlDoc.getElementsByTagName("newval")[0].childNodes[0].nodeValue;
    if (newval == 1) {
        document.getElementById(component+"_"+sid).checked = true;
        if (component == "featured") {
            // special case- featuring automatically sets frontpage
            document.getElementById("frontpage_"+sid).checked = true;
        }
    } else {
        document.getElementById(component+"_"+sid).checked = false;
    }
  }

}

function BL_getXmlHttpObject()
{
  var objXMLHttp=null
  if (window.XMLHttpRequest)
  {
    objXMLHttp=new XMLHttpRequest()
  }
  else if (window.ActiveXObject)
  {
    objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
  }
  return objXMLHttp
}

