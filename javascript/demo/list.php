<?php
require 'yield/medoo.php';

$db = new \Medoo\Medoo([
    'database_type' => 'mysql',
    'database_name' => 'openapi_localcache',
    'server' => 'rm-bp178946u0rk4ptjf.mysql.rds.aliyuncs.com',
    'username' => 'ops_new_phpfpm',
    'password' => 'tEkaFBOyCTD9'
]);

//取出fpm的映射关系
$data = $db->select('fpm_ip_info', [
    'id', 'out_ip', 'inner_ip'
]);

//取出生产环境的ip字段
$fpm_ips = $db->query('select communication_addr from openapi.shared_spo WHERE id=1')->fetchColumn();

//转为数组
$fpm_ips = json_decode($fpm_ips, true);

//将生产环境的ip和fpm映射表对应的外网ip合并
$res_data = array_combine(array_column($data, 'inner_ip', 'id'), array_column($data, 'out_ip', 'id'));

//重新处理为方便前端操作的数组
$data = array();
foreach ($fpm_ips as $key => $val) {
    if ($res_data[$val]) {
        $data[] = array(
            'id' => ($key + 1),
            'inner_ip' => $val,
            'out_ip' => $res_data[$val],
        );
    }
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
    <style>

        h3 {
            text-align: center;
        }

        .buton {
            border-top-width: 10px;
            margin-top: 50px;
            text-align: center;
        }

        .edit_button {
            display: none;
        }

        .error {
            color: red;
        }

        #data {
            /*display: table-cell;
            text-align: center;*/
            vertical-align: middle;
        }

        table {
            margin-left: auto;
            margin-right: auto;
        }

        input {
            width: 260px;
            height: 35px;
        }

        #message {
            height: 270px;
            width: 800px;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(37, 37, 37, 0.51);
            overflow: scroll;

        }

        /*json展示样式 start*/
        pre {
            outline: 1px solid #ccc;
            padding: 5px;
            margin: 5px;
        }

        .string {
            color: green;
        }

        .number {
            color: darkorange;
        }

        .boolean {
            color: blue;
        }

        .null {
            color: magenta;
        }

        .key {
            color: red;
        }

        /*json展示样式 end*/


    </style>
</head>
<body>

<!--创建一个点击按钮-->

<h3>部署测试</h3>

<!--创建一个表格-->
<div id="data">
    <table class="table">
        <?php
        foreach ($data as $ip) {
            echo "<tr id='tr-" . $ip['id'] . "'>
            <td>外网IP：<input type='text' name='ip_out' value='" . $ip['out_ip'] . "' ipid='" . $ip['id'] . "-out' disabled/></td>
            <td>内网IP：<input type='text' name='ip_in'value='" . $ip['inner_ip'] . "' ipid='" . $ip['id'] . "-in' disabled/></td>
            <td class='edit_check'><input type='button' value='点击编辑' onclick='edit_old_data(this," . $ip['id'] . ")' style='width:80px;height:35px;'/></td>
            <td class='edit_button'><input type='button' value='删除' onclick='deleteTrRow(this,1);' style='width:80px;height:35px;'/></td>
        </tr>";
        }


        ?>
        <tr>

            <!--   js异步更新操作，暂时不用 <td class='edit_button'><input type='button' value='编辑' onclick='update_old_data(" . $ip['id'] . ")' style='width:80px;height:35px;'/></td>
                    <td class='edit_button'><input type='button' value='删除' onclick='delete_old_data(" . $ip['id'] . ")' style='width:80px;height:35px;'/></td>
            -->

            <td>外网IP：<input type='text' name='ip_out' placeholder='请输入外网IP和端口:x.x.x.x:8080'/></td>

            <td>内网IP：<input type='text' name='ip_in' placeholder='请输入内网IP和端口:x.x.x.x:8080'/></td>
            <td><input type='button' value='+' onclick='addrow();' style='width:80px;height:35px;'/></td>
            <?php
            if ($data) {
                echo "<td><input type='button' value='-' onclick='deleteTrRow(this);' style='width:80px;height:35px;'/></td>";
            }
            ?>
        </tr>
    </table>
    <div class="buton">
        <input type="button" value="保存" id="save"/>
        <input type="button" value="清空记录信息" id="clear"/>
    </div>
</div>

<div id="message">
    <span class="tips"></span>
    <span class="success"></span>
    <span class="error"></span>
</div>
</body>

<script type="text/javascript" src="jquery.min.js"></script>


<!--创建添加行函数-->

<script type="text/javascript">
    function tips() {
        alert('该功能暂未启用')
    }

    function addrow() {

        var tables = $('.table');

        var addtr = $("<tr>" +
            "<td>外网IP：<input type='text' name='ip_out' placeholder='请输入外网IP和端口:x.x.x.x:8080'/></td>" +
            "<td>内网IP：<input type='text' name='ip_in' placeholder='请输入内网IP和端口:x.x.x.x:8080'/></td>" +
            "<td><input type='button' value='+' onclick='addrow();' style='width:80px;height:35px;'/></td>" +
            "<td><input type='button' value='-' onclick='deleteTrRow(this);' style='width:80px;height:35px;'/></td>" +
            "</tr>");

        addtr.appendTo(tables);

    }

    function deleteTrRow(tr, type=false) {

        if (type) {
            var msg = "您真的确定要删除吗？\n\n请确认！";
            if (confirm(msg) == true) {
                $(tr).parent().parent().remove();
            }
        } else {
            $(tr).parent().parent().remove();
        }
        //多一个parent就代表向前一个标签,

        //本删除范围为<td><tr>两个标签,即向前两个parent

        //如果多一个parent就会删除整个table

    }

</script>

<!--异步请求后台操作-->
<script>

    //下面代码到$.when原计划做异步处理的，暂时没用到.待后期优化
    var d1 = $.Deferred();
    var d2 = $.Deferred();
    var d3 = $.Deferred();


    $.when(d1, d2, d3).done(function (v1, v2, v3) {
        console.log(v1);
    });

    function clear_div() {
        $('.tips').empty();
        $('.success').empty();
        $('.error').empty();
    }

    //判断ip地址的合法性
    function checkIP(ip) {
        var regexp = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3}):(\d{1,4})$/;
        var valid = regexp.test(ip);
        if (!valid) {
            return false;
        }
        return true;
    }

    function check_value() {
        var result = true;

        $("input[name='ip_out']").each(function () {

            if (!checkIP($(this).val())) {
                alert($(this).val() + "外网IP地址不合法！");
                result = false;
            }

        })

        //验证内网
        $("input[name='ip_in']").each(function () {
            if (!checkIP($(this).val())) {
                alert($(this).val() + "内网IP地址不合法！");
                result = false;
            }

        })

        return result;
    }


    function edit_old_data(obj, id) {
        var id_name = '#tr-' + id;

        $(id_name).children('.edit_button').show();
        $(obj).hide();
        var in_ip = 'input[ipid="' + id + '-in"]';
        var out_ip = 'input[ipid="' + id + '-out"]';
        $(in_ip).attr('disabled', false); //将input元素设置为readonly
        $(out_ip).attr('disabled', false); //将input元素设置为readonly
    }

    //更新fpm主机
    function update_old_data(id) {
        var in_ip = 'input[ipid="' + id + '-in"]';
        var out_ip = 'input[ipid="' + id + '-out"]';
        var inner = $(in_ip).val();
        var outer = $(out_ip).val();
        $.post("index.php", {
                id: id,
                type: "update",
                in_ip: inner,
                out_ip: outer
            },
            function (data, status) {
                if (status == 'success') {
                    alert("数据更新成功");
                } else {
                    alert("数据更新失败");
                }

            });
    }


    //删除fpm主机ip
    function delete_old_data(id) {
        var msg = "您真的确定要删除吗？\n\n请确认！";
        if (confirm(msg) == true) {
            $.post("index.php", {
                    id: id,
                    type: "delete"
                },
                function (data, status) {
                    if (status == 'success') {
                        alert("数据删除成功");
                    } else {
                        alert("数据删除失败");
                    }

                });
        } else {
            return false;
        }

    }


    //开始执行ajax请求，提交后端验证ip和接口
    $(function () {

        $('#clear').bind('click', function () {
            clear_div();
        })

        $('#save').bind('click', function () {

            if (check_value()) {

                $('#save').hide();

                clear_div();

                //按步骤执行
                step_1();
            }

        })
    })

    //获取外网的数据
    function getOutValue() {
        var idList = '';

        idList = $('input[name="ip_out"]').map(function () {
            return $(this).val();
        }).get();
        return idList;
    }

    //获取内网ip
    function getInValue() {
        var idList = '';
        idList = $('input[name="ip_in"]').map(function () {
            return $(this).val();
        }).get();
        return idList;
    }

    function step_1(ips) {
        //第一步，验证ip和端口是否通畅
        $.ajax({
            method: "POST",
            url: '/index.php',
            async: true,
            data: {
                "outip": getOutValue(),
                "inip": getInValue(),
                "step": '1',
            },
            beforeSend: function () {
                $(".tips").append('正在进行验证，请稍等...' + '<br>');
            },
            success: function (data) {

                var new_data = '';
                var ok_message = '检测项返回结果 :' + '<br>';
                var error_message = '失败信息 :' + '<br>';
                new_data = $.parseJSON(data);

                if (new_data['success'] != false) {
                    jQuery.each(new_data['success'], function (i, val) {
                        jQuery.each(val, function (i, val) {
                            ok_message = ok_message + val + '<br>';
                        });
                        ok_message = ok_message + '<hr>';
                    });
                } else {
                    ok_message = '没有请求成功的信息' + '<br>';
                }

                if (new_data['error'] != false) {
                    jQuery.each(new_data['error'], function (i, val) {
                        jQuery.each(val, function (i, val) {
                            error_message = error_message + val + '<br>';
                        });
                        error_message = error_message + '<hr>';
                    });
                } else {
                    error_message = '无错误信息' + '<br>';
                }

                //展示信息
                $(".success").append(ok_message);
                $(".error").append(error_message);

                $('#save').show();
            }

        })
    }

    //json展示
    function syntaxHighlight(json) {
        if (typeof json != 'string') {
            json = JSON.stringify(json, undefined, 2);
        }
        json = json.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'key';
                } else {
                    cls = 'string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'boolean';
            } else if (/null/.test(match)) {
                cls = 'null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }


</script>
</html>
