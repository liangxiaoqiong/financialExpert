var publicObj = new Object({
  showLoading: function () {
    var loadHtml = $('#loading-layer').html();
    if (loadHtml === undefined) {
      var html = '<div class="loading-layer" id="loading-layer"><div class="loading-div"><div></div><div></div><div></div></div></div>';
      $('html').append(html);
    }
  },
  hideLoading: function () {
    var loadHtml = $('#loading-layer').html();
    if (loadHtml !== undefined) {
      $('#loading-layer').remove();
    }
  }
})
