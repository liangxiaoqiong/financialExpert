<?php
namespace AdminSmart\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function index(){
        $this->display('Index/index');
    }
    public function main(){
        $this->display('Index/main');
    }
    public function login(){
        $this->display('Login/login');
    }
    //违规预警
    public function violationWarn(){
        $this->display('ViolationWarn/index');
    }
    //违规预警详情
    public function warnInfo(){
        $this->display('ViolationWarn/warn_info');
    }
    //设置管理
    public function equipment(){
        $this->display('Equipment/index');
    }
    //系统管理
    public function system(){
        $this->display('System/index');
    }
    //监控中心
    public function monitor(){
        $this->display('MonitorCenter/index');
    }
}
