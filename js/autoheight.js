(function($) {
  var getDocHeight, setIframeHeight;

  getDocHeight = function(doc) {
    var body, height, html;
    doc = doc || document;
    body = doc.body;
    html = doc.documentElement;
    height = Math.max(body.scrollHeight, body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight);
    return height;
  };

  setIframeHeight = function(ifrm) {
    var doc, height;
    doc = ifrm.contentDocument ? ifrm.contentDocument : ifrm.contentWindow.document;
    ifrm.style.visibility = 'hidden';
    ifrm.style.height = '10px';
    height = getDocHeight(doc);
    ifrm.style.height = height + 4 + 'px';
    ifrm.style.visibility = 'visible';
    return height;
  };

  $('.static-content.autoheight iframe').on('load', function() {
    console.log('content loaded!');
    var count, heightPoll, iframe;
    iframe = this;
    count = 0;
    heightPoll = window.setInterval(function() {
      var height;
      count = count + 1;
      height = setIframeHeight(iframe);
      if (count > 9) {
        window.clearInterval(heightPoll);
      }
    }, 500);
  });

  $('.static-content.autoheight iframe').each(function() {
    setIframeHeight(this);
  });

})(jQuery);