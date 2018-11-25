
if (window.jutouSdk) {

    /**
     * 调用本地的json-rpc接口
     *
     * @param method
     * @param params
     * @param successCallback
     * @param errorCallback
     */
    window.devSdk.rpc = function (method, params, successCallback, errorCallback) {
        var url = '/index.php?r=api/json-rpc';
        url += '&i=' + (window.JT_CUSTOMER_ID ? window.JT_CUSTOMER_ID : 0);
        url += '&__method=' + method;
        url += "&__timestamp=" + new Date().getTime();

        var data = {
            jsonrpc: '2.0',
            method: method,
            params: params,
            id: window.jutouSdkGenerateUUID()
        };

        $.ajax({
            type: 'POST',
            async: false,
            dataType: 'json',
            contentType: 'application/json',
            url: url,
            data: JSON.stringify(data),
            error: function() {
                if (errorCallback) {
                    errorCallback({ code: 0, message: '网络错误' })
                }
            },
            success: function(json) {
                if (errorCallback && json.error) {
                    errorCallback(json.error);
                }
                if (successCallback && json.result) {
                    successCallback(json.result);
                }
            }
        });
    };

    /**
     * 提示框
     *
     * @param text
     * @param title
     * @param callback
     */
    window.jutouSdk.alert = function (text, title, callback) {
        if (typeof $.alert === 'function') {
            $.alert(text, title, callback);
            return;
        }
        if (window.layui !== undefined) {
            layui.use('layer', function () {
                var layer = layui.layer;
                var params = {};
                if (title === undefined) {
                    params = {
                        content: text
                    };
                } else if (typeof title === 'function') {
                    params = {
                        content: text
                    };
                    callback = title;
                } else {
                    params = {
                        title: title,
                        content: text
                    };
                }
                if (callback) {
                    params.yes = function (index) {
                        if (callback) {
                            callback();
                        }
                        layer.close(index);
                    };
                }
                layer.open(params);
            });
            return;
        }
        alert(text);
    };

    /**
     * 弹出提示
     *
     * @param message
     * @param ok
     * @param cancel
     */
    window.jutouSdk.confirm = function (message, ok, cancel) {
        // TODO 兼容多端
        $.confirm(message, ok, cancel);
    };

    /**
     * Toast信息
     *
     * @param content
     * @param url
     */
    window.jutouSdk.toast = function (content, url) {
        if (window.layui !== undefined) {
            layui.use('layer', function () {
                var layer = layui.layer;
                layer.msg(content);
                if (url !== undefined) {
                    setTimeout(function () {
                        jutouSdk.redirect(url);
                    }, 2000);
                }
            });
            return;
        }
        if ($.toast) {
            $.toast(content, 'text');
            return;
        }
        alert(content);
    };

    /**
     * 刷新页面
     */
    window.jutouSdk.reload = function () {
        location.reload();
    };

    /**
     * 跳转URL或页面
     *
     * @param url
     */
    window.jutouSdk.redirect = function (url) {
        if (url !=='' && url !== undefined )
        {
            window.location.href = url;
        }
    };
}
