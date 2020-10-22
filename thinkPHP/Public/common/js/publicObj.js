var publicObj;
publicObj = new Object({
  /**region JS函数用于获取url参数*/
  getQueryVariable: function (variable) {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i=0;i<vars.length;i++) {
      var pair = vars[i].split("=");
      if(pair[0] == variable){return pair[1];}
    }
    return(false);
  },
  /**endregion*/
  /**
   * 判断是否是手机
   * @param value
   * @returns {boolean}
   */
  isPhone: function (value) {
    var reg = /^1[3|4|5|7|8|9][0-9]\d{4,8}$/;
    return reg.test(value);
  },

  /**选择时间切换*/
  dayToTime: function (type, dateFormat) {
    var forT,//相隔毫秒数
      startT = 0, endT = 0;
    var nextMonth, lastMonth, nextMonthFirstDay;
    var myDate = new Date();
    myDate.setHours(0);
    myDate.setMinutes(0);
    myDate.setSeconds(0);
    myDate.setMilliseconds(0);
    var oneDay = 24 * 60 * 60 * 1000 - 1;//当天23:59:59
    switch (type) {
      case 1://今天
        startT = myDate.getTime();
        endT = startT + oneDay;
        break;
      case -1:
        forT = 86400000;//昨天
        var t = new Date(myDate - forT);
        startT = t.getTime();
        endT = startT + oneDay;
        break;
      case 7:
        forT = 604800000;//一周：今天-后7天
        var t = new Date(myDate + forT);
        startT = t.getTime();
        endT = startT + oneDay * 7;
        break;
      case 15:
        forT = 1296000000;//半个月：今天-后15天
        var t = new Date(myDate + forT);
        startT = t.getTime();
        endT = startT + oneDay * 15;
        break
      case 30:
        forT = 2592000000;//一个月：今天-后30天
        var t = new Date(myDate + forT);
        startT = t.getTime();
        endT = startT + oneDay * 30;
        break;
      case 90:
        forT = 7776000000;//三个月：今天-后3*30天
        var t = new Date(myDate + forT);
        startT = t.getTime();
        endT = startT + oneDay * 90;
        break;
      case 'prev_7':
        forT = 604800000;//最近一周：今天-前7天
        var t = new Date(myDate - forT + 2*oneDay);
        startT = t.getTime();
        endT = startT + oneDay * 7 - oneDay;
        break;
      case 1001: // 本月
        myDate.setDate(1);
        startT = myDate.getTime();
        nextMonth = myDate.getMonth() + 1;
        nextMonthFirstDay = new Date(myDate.getFullYear(), nextMonth, 1)
        endT = nextMonthFirstDay.getTime() - 1
        break;
      case 1002: // 上个月
        lastMonth = myDate.getMonth() - 1;
        nextMonth = myDate.getMonth();
        myDate.setMonth(lastMonth);
        myDate.setDate(1);
        startT = myDate.getTime();
        nextMonthFirstDay = new Date(myDate.getFullYear(), nextMonth, 1)
        endT = nextMonthFirstDay.getTime() - 1
        break;
      case 'prev_30':
        forT = 2592000000;//最近一月：今天-前30天
        var t = new Date(myDate - forT + 2*oneDay);
        startT = t.getTime();
        endT = startT + oneDay * 30 - oneDay;
        break;
      case 'prev_90':
        forT = 7776000000;//最近三个月：今天-前3*30天
        var t = new Date(myDate - forT + 2*oneDay);
        startT = t.getTime();
        endT = startT + oneDay * 90 - oneDay;
        break;
      case 'this_year':
        var t = new Date();
        endT = t.getTime();
        t.setFullYear(t.getFullYear());
        t.setMonth(0);
        t.setDate(1);
        startT = t.getTime();
        break;
      default:
        break;
    }
    var val1 = new Date(startT);
    var val1_ = publicObj.dateTime_Str(val1, dateFormat);
    var val2 = new Date(endT);
    var val2_ = publicObj.dateTime_Str(val2, dateFormat);
    if(+type === 0){
      val1_ = '';val2_ = '';
    }
    var arr = [val1_, val2_];
    return arr;
  },
  /**
   * //毫秒转时间2017-08-20 12:12:12*/
  dateTime_Str: function (time_, timeType) {
    var Y = time_.getFullYear();    //获取完整的年份(4位,1970-????)
    var M = publicObj.padNum(time_.getMonth() + 1, 2);       //获取当前月份(0-11,0代表1月)
    var D = publicObj.padNum(time_.getDate(), 2);        //获取当前日(1-31)
    var H = publicObj.padNum(time_.getHours(), 2);       //获取当前小时数(0-23)
    var Min = publicObj.padNum(time_.getMinutes(), 2);     //获取当前分钟数(0-59)
    var S = publicObj.padNum(time_.getSeconds(), 2);     //获取当前秒数(0-59)
    if (timeType === 'date') {
      var dataTime = Y + '-' + M + '-' + D;
    } else {
      var dataTime = Y + '-' + M + '-' + D + ' ' + H + ':' + Min + ':' + S;
    }
    return dataTime;
  },
  /**
   * 质朴长存法 =>不足位步0 by lifesinger
   * @param value
   */
  padNum: function (num, n) {
    var len = num.toString().length;
    while (len < n) {
      num = "0" + num;
      len++;
    }
    return num;
  },

  /**region 表单验证*/
  //多个循环表单非空验证
  /*
    var verifyRule = [
      { key: 'name', verify_type: 'required', error_text: '请输入**'},
      { key: 'name', verify_type: 'required_length', error_text: '请输入**'},
      { key: 'confirm_pwd', verify_type: 'equalTo', error_text: '两次密码输入不一致', equal_val: 'new_pwd'},
    ]
    if (!publicObj.verifyForm(verifyRule, verifyArr)) return false
    verifyRule:验证规则
    verifyArr:验证的数据
  */
  verifyForm: function (verifyRule, verifyArr) {
    // 传入表单数据，调用验证方法a
    let result = true
    try {
      verifyRule.forEach(function (value) {
        switch (value.verify_type) {
          case 'required':
            if (typeof verifyArr[value.key] === 'undefined' || verifyArr[value.key] === '') {
              publicObj.layerMsg(value.error_text)
              result = false
              throw Error()
            }
            break
          case 'required_length':
            if (typeof verifyArr[value.key] === 'undefined' || verifyArr[value.key].length <= 0) {
              publicObj.layerMsg(value.error_text)
              result = false
              throw Error()
            }
            break
          case 'equalTo'://两个值是否相等
            if (verifyArr[value.key] !== verifyArr[value.equal_val]) {
              publicObj.layerMsg(value.error_text)
              result = false
              throw Error()
            }
            break
          default:
            break
        }
      })
    } catch (e) {
    }
    return result
  },

  //表单验证报错提示
  errorHtml: function (text) {
    if ($('#form-error').html() === undefined) {
      publicObj.layerMsg(text)
    } else {
      var dom = $('#form-error .error-content').html()
      var html = '<div class="error-content"><i class="error-icon"></i><span>'+text+'</span></div>'
      if (dom === undefined) {
        $('#form-error').append(html);
      } else {
        $('#form-error .error-content').html('<i class="error-icon"></i><span>'+text+'</span>');
      }
      setTimeout(function(){
        $('#form-error .error-content').remove()
      }, 2000);
    }
  },
  /**endregion 表单验证*/

  /**region layer*/
  /*config = {
   type==1,div层 ；==2：iframe(默认)
   title  标题
   el    请求的url,div el
   area:[:弹出层宽度（缺省调默认值760px）,:弹出层高度（缺省调默认值80%）]
   direction： right-显示在右侧,更改了layer源码，引入layer必须用该layer版本
  }*/
  layerDialog: function (config, callback) {
    if (config.title == null || config.title == '') {
      config.title = false;
    }
    if (+config.type === 1) {
      config.el = $(config.el);
    } else {
      if (config.el == null || config.el == '') {
        config.el = "404.html";
      }
    }
    config.offset = config.offset ? config.offset : ['0', '']
    config.anim = config.anim ? config.anim : 2
    config.shadeClose = config.shadeClose ? config.shadeClose : false
    var skinIframe = ''
    var isMove = true //默认可拖动
    var isShade = false
    if (typeof config.direction !== 'undefined' && config.direction === 'right') {
      config.area = config.area ? config.area : ['100%', '100%']
      config.offset = ['0', '50%']
      config.anim = 7
      config.shadeClose = true
      skinIframe = 'dialog-right'
      isMove = false
      isShade = true
    }
    if (typeof (config.area) === 'undefined') {
      config.area = ['680px', '95%']
    }
    config.type = config.type ? config.type : 2
    parent.layer.open({
      type: config.type,
      area: config.area,
      fixed: true, //不固定
      move: isMove,
      anim: config.anim,
      offset: config.offset,
      title: config.title,
      closeBtn: config.closeBtn ? config.closeBtn : config.title == null || config.title == '' ? false : true,
      skin: (config.skin ? config.skin : 'layer-skin') + ' ' + skinIframe,
      shadeClose: config.shadeClose,
      shade: isShade,
      content: config.el,
      success: function (layero, index) {
        // layer弹层遮罩挡住窗体解决
        var mask = $(".layui-layer-shade");
        mask.appendTo(layero.parent());
        if (+config.type === 1) {
          window.parent.$('.main-top').addClass('layer-shade-after')
          var hasShade = $('.iframe-main').find('#layer-shade').html()
          if (typeof hasShade === 'undefined') {
            $('.iframe-main').append('<div id="layer-shade"></div>')
          }
        }
      },
      end: function () {
        if (+config.type === 1) {
          window.parent.$('.main-top').removeClass('layer-shade-after')
          var hasShade = $('.iframe-main').find('#layer-shade').html()
          if (typeof hasShade !== 'undefined') {
            $('.iframe-main').find('#layer-shade').remove();
          }
        }
        //弹框销毁后的回调
        if (typeof callback !== 'undefined') {
          callback()
        }
      }
    });
  },
  //layer.confirm弹框
  layerConfirm: function (config, callbackYes, callbackCancel, callbackEnd) {
    layer.confirm(config.el, {
      type: 1,
      title: config.title,
      skin: 'layer-skin',
      shadeClose: false,
      area: config.area || 'auto',
      offset: config.offset || ['0', 'calc(50% - 340px)'],
      btn: config.btn ? config.btn : ['确定', '取消'],
      success: function (layero) {
        // layer弹层遮罩挡住窗体解决
        var mask = $(".layui-layer-shade");
        mask.appendTo(layero.parent());
        window.parent.$('.main-top').addClass('layer-shade-after')
        var hasShade = $('.iframe-main').find('#layer-shade').html()
        if (typeof hasShade === 'undefined') {
          $('.iframe-main').append('<div id="layer-shade"></div>')
        }
        //兼容弹框div在滚动条下面，弹框显示内容偏下（代码正常）
        if (typeof config.self !== 'undefined') {
          config.self.$nextTick(function () {
            $('.iframe-main').parents('html, body').css({'overflow': 'auto'})
          })
        }
      },
      end: function (index) {
        window.parent.$('.main-top').removeClass('layer-shade-after')
        var hasShade = $('.iframe-main').find('#layer-shade').html()
        if (typeof hasShade !== 'undefined') {
          $('.iframe-main').find('#layer-shade').remove();
        }
        if (typeof config.self !== 'undefined') {
          config.self.$nextTick(function () {
            $('.iframe-main').parents('html, body').css({'overflow': 'hidden'})
          })
        }
        if (typeof callbackEnd !== 'undefined') {
          callbackEnd(index)//取消后的回调
        }
      },
      yes: function (index) {
        callbackYes(index)//确定后的回调
      },
      cancel: function (index) {
        if (typeof callbackCancel !== 'undefined') {
          callbackCancel(index)//取消后的回调
        }
      }
    })
  },

  //获取当前窗口
  getIframeIndex: function () {
    return parent.layer.getFrameIndex(window.name);
  },
  //关闭弹出框口 ifream
  layerFrameClose: function () {
    var index = publicObj.getIframeIndex()
    parent.layer.close(index);
  },

  //图片预览,type；undefined-单张图片预览,2-多图片json预览,3-多图片指定父容器
  imgPreview: function (imgData, type) {
    var imgDefault = {
      "title": "", //相册标题
      "id": 123, //相册id
      //"start": 0, //初始显示的图片序号，默认0
      "data": [   //相册包含的图片，数组格式
        {
          "alt": "",//图片名
          "pid": 666, //图片id
          "src": "", //原图地址
          "thumb": "" //缩略图地址
        }
      ]
    }
    if (+type === 2) {
      $.extend(true, imgDefault, imgData);
    } else if (+type === 3) {
      imgDefault = imgData
    } else {
      imgDefault.data[0].src = imgData
    }
    parent.layer.photos({
      photos: imgDefault,
      closeBtn:1,
      anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机（请注意，3.0之前的版本用shift参数）
    });
    if (+type !== 2) {
      $('#layui-layer-photos').find('.layui-layer-imgsee').css('display', 'none')
    }
  },

  /**
   * 重置layer.msg样式
   * content:msg内容
   * icon:msg类型{0：失败，1：成功}
   * iconName：
   * */
  layerMsg: function (content, config, callback) {
    var className = ''
    var iconName = ''
    config = config || {icon: 2}
    if (+config.icon === 1) {
      iconName = 'icon-success'
      className = 'layer-msg-success'
    } else {
      iconName = 'icon-fail'
      className = 'layer-msg-fail'
    }
    var html = '<div class="'+className+'"><i class="' + iconName + '"></i><div class="display-align">' + content + '</div></div>';
    layer.msg(html,{offset: ['35%', '43%']}, function () {
      if (typeof callback !== 'undefined') {
        callback()
      }
    });
  },

  confirmDel: function (callback, config) {
    parent.layer.confirm('', {
      title: config && config.title ? config.title : '确认要删除该项吗？',
      skin: 'layer-confirm-del',
      btn: ['删除', '取消']
    }, function (index) {
      callback(index)
    })
  },

})

