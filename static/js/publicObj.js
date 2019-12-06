var publicObj;
var protocolUrl = window.location.protocol
$(function () {
  //绑定需要浮动的表头
  $(".rule-single-select").ruleSingleSelect();

  //清空input 框的值
  $(document).on('click', '.input-clear', function () {
    $(this).parent('.input-control').find('input').val('')
  })

  /**region checkbox 快捷全选、单选*/
  $(document).on('click', '.check-all-btn', function () {
    var isCheckAll = $('.check-all').is(':checked')
    publicObj.checkAll(!isCheckAll)
  })
  $(document).on('change', '.check-all', function () {
    var isCheckAll = $(this).is(':checked')
    publicObj.checkAll(isCheckAll)
  })
  /**endregion*/

})
publicObj = new Object({
  uploadUrl: protocolUrl + '../../json/upload_file.json', //上传文件服务路径
  isCheckAll:  $('.check-all').is(':checked'),

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

  /**jedate时间插件*/
  jeDate_: function (id, config) {
    var data = {
      dateCell: "#" + id,
      format: "YYYY-MM-DD hh:mm:ss",
      isinitVal: false, //给默认值
      isTime: true, //isClear:false,
      okfun: function (val) {
      }
    }
    if (typeof config !== 'undefined') {
      $.extend(true, data, config);
    }
    jeDate(data);
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
  //表单验证样式:jquery.validate插件
  /*validation_error: function (name) {
    $("input[name='"+name+"']").addClass('error');
    $("#"+name+"-error").html(message);
    $("#"+name+"-error").removeClass('valid');
    $("#"+name+"-error").show();
  },*/

  verifyForm2: function (verifyArr) {
    var empty_len = 0
    try {
      verifyArr.forEach(function (value) {
        var input_val = $("input[name='"+value.key+"']").val();
        if (value.input_type === 'select') {
          input_val = $("select[name='"+value.key+"'] option:selected").val()
        }
        switch (value.verify_type) {
          case 'required':
            if (input_val === '') {
              var dom;
              var html = '<label class="error">'+value.error_text+'</label>'
              if (value.input_type === 'select') {
                dom = $("select[name='"+value.key+"']").parents('dd')
              } else {
                dom = $("input[name='"+value.key+"']").parents('dd')
              }
              if (dom.find('label.error').html() === undefined) {
                dom.append(html)
              } else {
                dom.find('label.error').html(value.error_text);
              }
              setTimeout(function(){
                dom.find('label.error').remove()
              }, 2000);
              empty_len++
            }
            break
          default:
            break
        }
      })
    } catch (e) {
    }
    return empty_len
  },
  //多个循环表单非空验证
  /*var verifyArr = [
    { key: 'phone', verify_type: 'required', error_text: '请输入手机号码', input_type: 'input'},
    { key: 'phone', verify_type: 'phone', error_text: '请输入有效的手机号码', input_type: 'select'},
  ]
  if (!publicObj.verifyForm(verifyArr)) return false*/
  verifyForm: function (verifyArr) {
    var result = true
    try {
      verifyArr.forEach(function (value) {
        var input_val = $("input[name='"+value.key+"']").val()
        if (value.input_type === 'select') {
          input_val = $("select[name='"+value.key+"'] option:selected").val()
        } else if (value.input_type === 'textarea') {
          input_val = $("textarea[name='"+value.key+"']").val()
        }
        switch (value.verify_type) {
          case 'required':
            if (input_val === '') {
              publicObj.errorHtml(value.error_text)
              result = false
              throw Error();
            }
            break
          case "phone":
            if (!publicObj.isPhone(input_val)) {
              publicObj.errorHtml(value.error_text)
              result = false
              throw Error();
            }
            break
          case "equalTo": //两个变量值一致
            var equal_val = $("input[name='"+value.equal_val+"']").val()
            if (value.input_type === 'select') {
              equal_val = $("select[name='"+value.equal_val+"'] option:selected").val()
            }
            if (input_val !== equal_val) {
              publicObj.errorHtml(value.error_text)
              result = false
              throw Error();
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

  /**region checkbox 快捷全选*/
  checkAll: function (isCheckAll) {
    $('.check-all').prop("checked", isCheckAll);
    $(".check-td").prop("checked", isCheckAll);
    $('.check-all-btn').text(isCheckAll ? '取消' : '全选')
  },
  //获取选中的checkbox value
  getCheckBox: function () {
    var chk_value = [];
    $('.check-td:checked').each(function () {
      chk_value.push($(this).val());
    });
    if (chk_value.length === 0) {
      publicObj.layerMsg('你还没有选择任何内容');
      return false;
    }else{
      return chk_value;
    }
  },
  /**endregion*/

  /**region 文件上传*/
  // 上传文件
  uploadFile: function (config, callback) {
    var form = new FormData();
    if (typeof config.file === 'object') {
      var file = config.file
    } else {
      var el = $(config.el)[0]
      if (typeof el === 'undefined') {
        layer.msg('没有需要上传的文件')
        return false;
      }
      var file = el.files[0]
      if (typeof file === 'undefined') {
        layer.msg('没有需要上传的文件')
        return false;
      }
    }
    form.file = file
    form.id = config.id || ''
    form.name = file.name
    form.type = file.type
    form.lastModifiedDate = file.lastModifiedDate
    form.size = file.size
    publicObj.ajaxUpload(form, callback)
  },
  //多文件上传
  uploadMultiple: function (config, callback) {
    var filesList = $(config.el)[0].files
    if (filesList.length <= 0) {
      layer.msg('没有需要上传的文件')
      return false
    } else {
      for (var i = 0; i < filesList.length; i++) {
        var form = new FormData();
        var value = filesList[i]
        form.file = value
        form.id = config.id || ''
        form.name = value.name
        form.type = value.type
        form.lastModifiedDate = value.lastModifiedDate
        form.size = value.size
        publicObj.ajaxUpload(form, callback)
      }
    }
  },
  //ajax请求文件服务器保存
  ajaxUpload: function (form, callback) {
    $.ajax({
      url: publicObj.uploadUrl,
      data: form,
      processData: false,
      contentType: false,
      type: 'POST',
      dataType: 'JSON',
      success: function (result) {
        if (result.code === 1) {
          if (typeof (callback) === 'function') {
            callback(result.data);
            return false;
          }
        } else {
          publicObj.layerMsg(result.debug);
        }
      },
      error: function (result) {
        publicObj.layerMsg('网络不给力哦...');
      }
    });
  },
  /**endregion*/

  /**region 圆形进度条*/
  /*
   * circleChart 不兼容IE
   * @param value
   * elName:'.class'或'#id' //样式名
   * textConfig: {
   *   text_t: ''
   * }
   * publicObj.circleChartCus('.circleChart#1', {
    backgroundColor: "#FFCE02", // 进度条之外颜色
  }, {text_t: '支出收入比'})
   */
  circleChartCus: function (elName, configData, textConfig) {
    var configDefault = {
      color: "#1A8FFC", // 进度条颜色
      widthRatio: 0.06, // 进度条宽度%
      unit: "percent",
      lineCap: "butt",
      counterclockwise: false, // 进度条反方向
      size: 100, // 圆形大小
      startAngle: 75, // 进度条起点
      text: true,
      onDraw: function (el, circle) {
        var val = Math.round(circle.value) + "%"
        var text = '<span class="circleChart_text-container"><span class="t">'+textConfig.text_t+'</span><span class="val">' + val + '</span></span>'
        circle.text(text)
      }
    }
    $.extend(true, configDefault, configData); //合并两对象数据，相同键值swiperData覆盖swiperDefault
    var circleChart = $(elName).circleChart(configDefault)
    return circleChart;
  },

  //radialIndicator
  diyRadialIndicator: function (elName, configData) {
    var initVal = Number($(elName).data('value'))
    var initTitle = $(elName).data('title')
    if (typeof initTitle !== 'undefined' && initTitle !== '') {
      var html = '' +
        '<div class="radialIndicator_text">' +
        '<div class="radialIndicator_text_container">' +
        '<span class="t">'+initTitle+'</span>' +
        '<span class="val">'+initVal+'%</span>' +
        '</div>' +
        '</div>'
      var dom = $(elName).find('.radialIndicator_text').html()
      if (dom == undefined) {
        $(elName).append(html)
      }
    }
    var configDefault = {
      barColor: '#1A8FFC',
      barBgColor: '#F0F0F0',
      barWidth: 4,
      initValue: initVal,
      percentage: true,
      displayNumber: false,
      radius: 56
    }
    if (typeof configData !== 'undefined') {
      $.extend(true, configDefault, configData);
    }
    var data = $(elName).radialIndicator(configDefault)
    return data;
  },

  /**endregion*/


  /**region echart*/
  //柱状图,elName:样式id
  echartBar: function (elName, optionData) {
    var myChart = echarts.init(document.getElementById(elName));
    var option = {
      title: {
        text: '千元',
        x: '2%',
        y: '2%',
        textStyle:{
          color:'#666666',
          fontSize: 12,
          fontWeight:'normal',
        },
        textAlign: 'left'
      },
      backgroundColor: '#F7F8FA',
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow',
        },
        backgroundColor: '#fff',
        textStyle: {
          color: '#666666'
        }
      },
      legend: {
        show: false
      },
      grid:{
        x:'8%',
        width:'90%',
        y:'14%',
      },
      xAxis: {
        data: [],
        axisLabel: {
          rotate: 60,//60度角倾斜显示
        },
        axisLine: {
          lineStyle: {
            color: '#666666'
          }
        },
        axisTick:{
          show:false,
        },
      },
      yAxis: [
        {
          splitLine: {show: false},
          axisLine: {
            lineStyle: {
              color: '#666666',
            }
          },

          axisLabel:{
            formatter:'{value} ',//左侧纵坐标值
          }
        }
      ],
      series: [
        {
          name: '收入',
          type: 'bar',
          barWidth: 6,
          itemStyle: {
            normal: {
              shadowOffsetX: 4,//柱状图像右移动4px的投影
              shadowColor: 'rgba(255,83,61,0.1)',//柱状图像右移动4px的投影的颜色
              barBorderRadius: 3,
              color: new echarts.graphic.LinearGradient(
                0, 0, 0, 1,
                [
                  {offset: 0, color: '#FFDDBB'},
                  {offset: 1, color: '#FF533D'}
                ]
              )
            }
          },
          data: []
        }
      ]
    };
    $.extend(true, option, optionData); //合并两对象数据，相同键值swiperData覆盖swiperDefault
    var chart = myChart.setOption(option);
    return chart;
  },
  //饼状图
  echartPie: function (elName, optionData) {
    var myChart = echarts.init(document.getElementById(elName));
    var option = {
      tooltip: {
        trigger: 'item',
        formatter: "{a} <br/>{b}: {c} ({d}%)"
      },
      legend: {
        show: false,
      },
      series: [
        {
          name:'',
          type:'pie',
          radius: ['25%', '100%'],
          avoidLabelOverlap: false,
          label: {
            show: false
          },
          labelLine: {
            normal: {
              show: false
            }
          },
          legendHoverLink: false,
          overAnimation: false,
          hoverOffset: 0,
          selectedOffset: 0,
          itemStyle: {
            borderColor: '#fff',
            borderWidth: 4,
          },
          data:[
            {value:5, name:'', itemStyle: {color: '#8DAFFF'}},
            {value:5, name:'', itemStyle: {color: '#FFBC52'}},
            {value:5, name:'', itemStyle: {color: '#1A8FFC'}},
            {value:5, name:'', itemStyle: {color: '#BFE0FF'}},
          ]
        }
      ]
    };
    $.extend(true, option, optionData);
    var chart = myChart.setOption(option);
    return chart;
  },
  /**endregion*/

  /**region layer*/
  /*config = {
   type==1,div层 ；==2：iframe(默认)
   title  标题
   el    请求的url,div el
   area:[:弹出层宽度（缺省调默认值760px）,:弹出层高度（缺省调默认值80%）]
   direction： right-显示在右侧
  }*/
  layerShow: function (config, direction) {
    if (config.title == null || config.title == '') {
      config.title = false;
    }
    if (typeof (config.area) === 'undefined') {
      config.area = ['700px', '95%']
    }
    if (+config.type === 1) {
      config.el = $(config.el);
    } else {
      if (config.el == null || config.el == '') {
        config.el = "404.html";
      }
    }
    config.offset = config.offset ? config.offset : ['2.5%', '']
    config.anim = config.anim ? config.anim : 2
    if (typeof direction !== 'undefined' && direction === 'right') {
      config.area = ['45%', '100%']
      config.offset = 'r'
      config.anim = 7
    }
    return parent.layer.open({
      type: config.type ? config.type : 2,
      area: config.area,
      fix: false, //不固定
      anim: config.anim,
      offset: config.offset,
      title: config.title,
      closeBtn: config.closeBtn ? config.closeBtn : config.title == null || config.title == '' ? false : true,
      skin: config.skin ? config.skin : 'layer-skin',
      shadeClose: typeof direction !== 'undefined' && direction === 'right',
      content: config.el,
    });
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

  //信息提示：content-提示文字，msgType-消息类型[1:成功，2||undefined-失败]，iconName-图标类型[默认不传]
  layerMsg: function (content, msgType, iconName) {
    if (+msgType === 1) {
      if (typeof(iconName) === 'undefined' || typeof(iconName) === '') {
        iconName = 'icon-success';
      }
      var html = '<div class="layer-msg-success"><i class="' + iconName + '"></i><div class="display-align">' + content + '</div></div>';
    } else {
      if (typeof(iconName) === 'undefined' || typeof(iconName) === '') {
        iconName = 'icon-fail';
      }
      var html = '<div class="layer-msg-fail"><i class="' + iconName + '"></i><div class="display-align">' + content + '</div></div>';
    }
    parent.layer.msg(html);
    parent.$('[class^="layer-msg-"]').parents('.layui-layer').css({'background': 'none'});
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

  /**region iframe各子页面的左侧菜单数据*/
  //parentType：父页面的类型, thisMenuType：当前页面菜单类型
  getIframeMenu: function (parentType, thisMenuType) {
    var menuListData = []
    switch (parentType) {
      case 'accountManage': //账目管理
        menuListData = [
          {
            menuList: [
              {
                type: 'income_list',
                name: '收入管理',
                url: 'accountManage/income_expenditure_list.html?type=income',
                is_selected: thisMenuType === 'income_list' ? 1 : 0
              },
              {
                type: 'expenditure_list',
                name: '支出管理',
                url: 'accountManage/income_expenditure_list.html?type=expenditure',
                is_selected: thisMenuType === 'expenditure_list' ? 1 : 0
              },
              {
                type: 'receivable_list',
                name: '应收管理',
                url: 'accountManage/receivable_payable_list.html?type=receivable',
                is_selected: thisMenuType === 'receivable_list' ? 1 : 0
              },
              {
                type: 'payable_list',
                name: '应付管理',
                url: 'accountManage/receivable_payable_list.html?type=payable',
                is_selected: thisMenuType === 'payable_list' ? 1 : 0
              },
              {
                type: 'reimbursement_list',
                name: '报销管理',
                url: 'accountManage/reimbursement_list.html',
                is_selected: thisMenuType === 'reimbursement_list' ? 1 : 0
              },
              {
                type: 'customer_list',
                name: '客户管理',
                url: 'customer/customer_list.html',
                is_selected: thisMenuType === 'customer_list' ? 1 : 0
              },
            ],
          },
          {
            menuList: [
              {
                type: 'sponsor_category_list',
                name: '经手人分类',
                url: 'category/category_list.html?type=sponsor',
                is_selected: thisMenuType === 'sponsor_category_list' ? 1 : 0
              },
              {
                type: 'income_category_list',
                name: '收入项分类',
                url: 'category/category_list.html?type=income',
                is_selected: thisMenuType === 'income_category_list' ? 1 : 0
              },
              {
                type: 'expenditure_category_list',
                name: '支出项分类',
                url: 'category/category_list.html?type=expenditure',
                is_selected: thisMenuType === 'expenditure_category_list' ? 1 : 0
              },
              {
                type: 'receivable_category_list',
                name: '应收款分类',
                url: 'category/category_list.html?type=receivable',
                is_selected: thisMenuType === 'receivable_category_list' ? 1 : 0
              },
              {
                type: 'payable_category_list',
                name: '应付款分类',
                url: 'category/category_list.html?type=payable',
                is_selected: thisMenuType === 'payable_category_list' ? 1 : 0
              },
              {
                type: 'paytype_list',
                name: '支付账户',
                url: 'category/paytype_list.html',
                is_selected: thisMenuType === 'paytype_list' ? 1 : 0
              },
              {
                type: 'customer_category_list',
                name: '客户分类',
                url: 'category/category_list.html?type=customer',
                is_selected: thisMenuType === 'customer_category_list' ? 1 : 0
              },
              {
                type: 'customertag_category_list',
                name: '客户身份标签',
                url: 'category/category_list.html?type=customertag',
                is_selected: thisMenuType === 'customertag_category_list' ? 1 : 0
              },
            ]
          }
        ]
        break
      case 'memorandum': //计划任务
        menuListData = [
          {
            menuList: [
              {
                type: 'memorandum_unprocessed',
                name: '未处理任务',
                url: 'memorandum/memorandum_list.html?type=memorandum_unprocessed',
                is_selected: thisMenuType === 'memorandum_unprocessed' ? 1 : 0
              },
              {
                type: 'memorandum_processed',
                name: '已处理任务',
                url: 'memorandum/memorandum_list.html?type=memorandum_processed',
                is_selected: thisMenuType === 'memorandum_processed' ? 1 : 0
              },
            ]
          },
          {
            menuList: [
              {
                type: 'memorandum_category_list',
                name: '事项分类',
                url: 'category/category_list.html?type=memorandum',
                is_selected: thisMenuType === 'memorandum_category_list' ? 1 : 0
              },
            ]
          }
        ]
        break
      case 'employeeManage': //员工管理
        menuListData = [
          {
            menuList: [
              {
                type: 'employee_list',
                name: '员工管理',
                url: 'employee/employee_list.html',
                is_selected: thisMenuType === 'employee_list' ? 1 : 0
              },
              {
                type: 'payroll_list',
                name: '工资管理',
                url: 'payroll/payroll_list.html',
                is_selected: thisMenuType === 'payroll_list' ? 1 : 0
              },
            ]
          },
          {
            menuList: [
              {
                type: 'department_list',
                name: '部门管理',
                url: 'employee/department_list.html',
                is_selected: thisMenuType === 'department_list' ? 1 : 0
              }
            ]
          }
        ]
        break
      case 'asset': //资产管理
        menuListData = [
          {
            menuList: [
              {
                type: 'asset_list',
                name: '资产管理',
                url: 'asset/asset_list.html?type=asset_list',
                is_selected: thisMenuType === 'asset_list' ? 1 : 0
              },
            ]
          },
          {
            menuList: [
              {
                type: 'asset_category_list',
                name: '资产分类管理',
                url: 'category/category_list.html?type=asset',
                is_selected: thisMenuType === 'asset_category_list' ? 1 : 0
              },
            ]
          }
        ]
        break
      case 'statistics':
        menuListData = [
          {
            menuList: [
              {
                type: 'income_expenditure',
                name: '收支报表',
                url: 'statistics/income_expenditure_report.html',
                is_selected: thisMenuType === 'income_expenditure' ? 1 : 0
              },
              {
                type: 'receivable_payable',
                name: '应收应付报表',
                url: 'statistics/receivable_payable_report.html',
                is_selected: thisMenuType === 'receivable_payable' ? 1 : 0
              },
            ]
          }
        ]
        break;
      case 'system': //系统设置
        menuListData = [
          {
            menuList: [
              {
                type: 'company_manage',
                name: '企业管理',
                url: 'company/company_manage.html',
                is_selected: thisMenuType === 'company_manage' ? 1 : 0
              },
              {
                type: 'company_vip',
                name: '企业VIP',
                url: 'company/company_vip.html',
                is_selected: thisMenuType === 'company_vip' ? 1 : 0
              },
              {
                type: 'user_list',
                name: '系统用户',
                url: 'user/user_list.html',
                is_selected: thisMenuType === 'user_list' ? 1 : 0
              },
              {
                type: 'auth_setting',
                name: '审核设置',
                url: 'user/auth_setting.html',
                is_selected: thisMenuType === 'auth_setting' ? 1 : 0
              },
              {
                type: 'log_record',
                name: '日志记录',
                url: 'log/log_operation.html',
                is_selected: thisMenuType === 'log_record' ? 1 : 0
              },
            ]
          }
        ]
        break
      default:
        break
    }
    return menuListData
  },
  getIframeMenuHtml: function (parentType, thisMenuType) {
    var menuData = publicObj.getIframeMenu(parentType, thisMenuType)
    var html = ''
    var menu_quick = ''
    switch (parentType) {
      case 'accountManage': //账目管理
        menu_quick = '<div class="menu-title">' +
          '<a class="febtn-default" onclick="publicObj.layerShow({title: \'记一笔\', el: \'accountManage/account_edit.html\'})">' +
          '<i class="feimg-edit-white"></i><span>记一笔</span>' +
          '</a>' +
          '</div>'
        break
      case 'memorandum': //计划任务
        menu_quick = '<div class="menu-title">' +
          '<a class="febtn-default" onclick="publicObj.layerShow({title: \'新增任务\', el: \'memorandum/memorandum_edit.html\'})">' +
          '<i class="feimg-edit-white"></i><span>新增任务</span>' +
          '</a>' +
          '</div>'
        break
      case 'employeeManage': //员工管理
        menu_quick = '<div class="menu-title">' +
          '<a class="febtn-default" onclick="publicObj.layerShow({title: \'新增员工\', el: \'employee/employee_edit.html\'})">' +
          '<i class="feimg-edit-white"></i><span>新增员工</span>' +
          '</a>' +
          '</div>'
        break
      case 'asset': //资产管理
        menu_quick = '<div class="menu-title">' +
          '<a class="febtn-default" onclick="publicObj.layerShow({title: \'新增资产\', el: \'asset/asset_edit.html\'})">' +
          '<i class="feimg-edit-white"></i><span>新增资产</span>' +
          '</a>' +
          '</div>'
        break
      default:
        break
    }
    var menu_ul = '<ul>'
    menuData.forEach(function (value) {
      menu_ul += '<li>'
      value.menuList.forEach(function (val2) {
        var isActive = +val2.is_selected === 1 ? 'active' : ''
        menu_ul += '<a class="' + isActive + '" href="' + val2.url + '" target="mainframe">' + val2.name + '</a>'
      })
      menu_ul += '</li>'
    })
    menu_ul += '</ul>'
    html += menu_quick + '' + menu_ul
    parent.$('#iframe-menu').show()
    parent.$('#iframe-menu').html(html)
  },
  /**endregion*/
})

/**region 单选下拉框*/
$.fn.ruleSingleSelect = function () {
  var singleSelect = function (parentObj) {
    if (parentObj.find("select").length == 0) {
      parentObj.remove();
      return false;
    }
    parentObj.addClass("single-select"); //添加样式
    parentObj.children('.boxwrap').remove(); //防止重复初始化
    //parentObj.children().hide(); //隐藏内容
    parentObj.children().css({ position: 'absolute', zIndex: -999, opacity: 0 }); //隐藏内容
    var divObj = $('<div class="boxwrap"></div>').prependTo(parentObj); //前插入一个DIV
    //创建元素
    var titObj = $('<a class="select-tit" href="javascript:;"><span></span></a>').appendTo(divObj);
    var itemObj = $('<div class="select-items"><ul></ul></div>').appendTo(divObj);
    var selectObj = parentObj.find("select").eq(0); //取得select对象
    //遍历option选项
    selectObj.find("option").each(function (i) {
      var indexNum = selectObj.find("option").index(this); //当前索引
      var liObj = $('<li>' + $(this).text() + '</li>').appendTo(itemObj.find("ul")); //创建LI
      if ($(this).prop("selected") == true) {
        liObj.addClass("selected");
        titObj.find("span").text($(this).text());
      }
      //检查控件是否启用
      if ($(this).prop("disabled") == true) {
        liObj.css("cursor", "default");
        return;
      }
      //绑定事件
      liObj.click(function () {
        $(this).siblings().removeClass("selected");
        $(this).addClass("selected"); //添加选中样式
        selectObj.find("option").prop("selected", false);
        selectObj.find("option").eq(indexNum).prop("selected", true); //赋值给对应的option
        titObj.find("span").text($(this).text()); //赋值选中值
        itemObj.slideToggle('hide');//itemObj.hide(); //隐藏下拉框
        selectObj.trigger("change"); //触发select的onchange事件
        //alert(selectObj.find("option:selected").text());
      });
    });
    //设置样式
    //titObj.css({ "width": titObj.innerWidth(), "overflow": "hidden" });
    //itemObj.children("ul").css({ "max-height": $(document).height() - titObj.offset().top - 62 });

    //检查控件是否启用
    if (selectObj.prop("disabled") == true) {
      titObj.css("cursor", "default");
      return;
    }
    //绑定单击事件
    titObj.click(function (e) {
      $('.jedatebox').hide()
      e.stopPropagation();
      if (itemObj.is(":hidden")) {
        //隐藏其它的下位框菜单
        $(".single-select .select-items").hide();
        $(".single-select .arrow").hide();
        //位于其它无素的上面
        itemObj.css("z-index", "10");
        //显示下拉框
        itemObj.slideToggle('show');//itemObj.show();

        //5.0新增判断下拉框上或下呈现
        if(parentObj.parents('.tab-content').length > 0){
          var tabObj = parentObj.parents('.tab-content');
          //容器高度-下拉框TOP坐标值-容器TOP坐标值
          var itemBttomVal = tabObj.innerHeight() - itemObj.offset().top + tabObj.offset().top - 12;
          if(itemBttomVal < itemObj.height()){
            var itemTopVal = tabObj.innerHeight() - itemBttomVal - 61;
            if(itemBttomVal > itemTopVal){
              itemObj.children('ul').height(itemBttomVal);
            }else{
              if(itemTopVal < itemObj.height()){
                itemObj.children('ul').height(itemTopVal);
              }
              if(!parentObj.hasClass('up')){
                parentObj.addClass("up"); //添加样式
              }
            }
          }
        }

      } else {
        //位于其它无素的上面
        itemObj.css("z-index", "");
        //隐藏下拉框
        itemObj.hide();
      }
    });
    //绑定页面点击事件
    $(document).click(function (e) {
      selectObj.trigger("blur"); //触发select的onblure事件
      itemObj.hide(); //隐藏下拉框
    });
  };
  return $(this).each(function () {
    singleSelect($(this));
  });
}
/**endregion*/

