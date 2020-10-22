/*region 全局响应式布局,使用transform缩放*/
var layWidth = 1920;
window.onload = function () {
  var scale = document.body.clientWidth / layWidth;
  if (document.getElementById('transform-box') != null) {
    document.getElementById('transform-box').style.transform = 'scale('+scale+')';
  }
  if (typeof vm.pageHeight !== 'undefined') {
    vm.pageHeight = document.body.clientHeight / (document.body.clientWidth / layWidth)
  }
  //layer iframe页面
  if (typeof vm.pageWidth !== 'undefined') {
    vm.pageWidth = (document.body.clientWidth / 2) / (document.body.clientWidth / layWidth)
  }

  var loadHtml = document.getElementById('load-box')
  if (typeof loadHtml !== "undefined") {
    document.getElementById('load-box').remove()
  }
};
(function () {
  var loadHtml = '<div id="load-box" style=""></div>'
  document.body.insertAdjacentHTML('afterbegin', loadHtml);
  var throttle = function (type, name, obj) {
    obj = obj || window;
    var running = false;
    var func = function () {
      if (running) {
        return;
      }
      running = true;
      requestAnimationFrame(function () {
        obj.dispatchEvent(new CustomEvent(name));
        running = false;
      });
    };
    obj.addEventListener(type, func);
  };

  /* init - you can init any event */
  throttle("resize", "optimizedResize");
})();
window.addEventListener("optimizedResize", function () {
  var scale = document.body.clientWidth / layWidth;
  if (document.getElementById('transform-box') != null) {
    document.getElementById('transform-box').style.transform = 'scale('+scale+')';
  }
  if (typeof vm.pageHeight !== 'undefined') {
    vm.pageHeight = document.body.clientHeight / (document.body.clientWidth / layWidth)
  }

  //layer iframe页面
  if (typeof vm.pageWidth !== 'undefined') {
    vm.pageWidth = (document.body.clientWidth / 2) / (document.body.clientWidth / layWidth) - 15
  }
});
/*endregion*/

