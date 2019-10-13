<?php

/** @var \common\components\View $this */

\common\assets\DebugAsset::register($this);
\common\assets\WeixinJssdk::register($this);
\common\assets\JutouSDK::register($this);

?>

<br />

<button onclick="window.jutouSdk.nativeScanQrcode()">测试 nativeScanQrcode</button>
<br />

<button onclick="window.jutouSdk.nativePopRoute()">测试 nativePopRoute</button>
<br />

<button onclick="window.jutouSdk.nativeToastInfo('toast info');return false;">测试 nativeToastInfo</button>
<br />
<button onclick="window.jutouSdk.nativeToastSmile('toast smile');return false;">测试 nativeToastSmile</button>
<br />
<button onclick="window.jutouSdk.nativeToastSad('toast sad');return false;">测试 nativeToastSad</button>
<br />

<button onclick="testNativeModalAlert();return false;">测试 nativeModalAlert</button>
<script>
    function testNativeModalAlert() {
        window.jutouSdk.nativeModalAlert('是否要删除？', '删除后即将无法恢复', [
            {
                text: '取消',
                onPress: function () {
                    console.log('testNativeModalAlert cancel');
                }
            },
            {
                text: '确认',
                onPress: function () {
                    console.log('testNativeModalAlert confirm');
                }
            }
        ]);
    }
</script>
<br />

<button onclick="testNativeModalOperation();return false;">测试 nativeModalOperation</button>
<script>
    function testNativeModalOperation() {
        window.jutouSdk.nativeModalOperation([
            {
                text: '取消',
                onPress: function () {
                    console.log('nativeModalOperation cancel');
                }
            },
            {
                text: '确认',
                onPress: function () {
                    console.log('nativeModalOperation confirm');
                }
            }
        ]);
    }
</script>
<br />

<button onclick="testNativeModalPrompt();return false;">测试 nativeModalPrompt</button>
<script>
    function testNativeModalPrompt() {
        window.jutouSdk.nativeModalPrompt('用户名', '请输入您的用户名', 'default', 'Better', function (value) {
            console.log('testNativeModalPrompt submit', value);
        }, function () {
            console.log('testNativeModalPrompt cancel');
        });
    }
</script>
<br />

<button onclick="window.jutouSdk.nativeModalIndicatorShow('加载中');setTimeout(function(){ window.jutouSdk.nativeModalIndicatorHide(); }, 3000);return false;">测试 nativeModalIndicatorShow</button>
<br />

<button onclick="window.jutouSdk.nativePushRoute('videoPlayer')">测试 打开视频播放页面</button>
<br />

<br />

<script type="text/javascript">
    function nativeShowRightButtonDemo() {
        window.jutouSdk.nativeShowRightButton('保存', function () {
            console.log('点击了右边的保存按钮');
        });
    }
</script>
<button onclick="nativeShowRightButtonDemo()">测试 显示右边菜单(文字)</button>
<br />
<button onclick="window.jutouSdk.nativeHideRightButton()">测试 隐藏右边菜单</button>
<br />

<br />

<button onclick="window.jutouSdk.nativeSetStatusBarStyle('default')">测试 nativeSetStatusBarStyle default</button>
<br />
<button onclick="window.jutouSdk.nativeSetStatusBarStyle('light-content')">测试 nativeSetStatusBarStyle light-content</button>
<br />
<button onclick="window.jutouSdk.nativeSetStatusBarStyle('dark-content')">测试 nativeSetStatusBarStyle dark-content</button>
<br />

<br />

<script type="text/javascript">
    window.imageId = null;
    function testWxChooseImage() {
        window.jutouSdk.wxChooseImage({
            count: 1, // 默认9
            sizeType: ['original', 'compressed'], // 可以指定是原图还是压缩图，默认二者都有
            sourceType: ['album', 'camera'], // 可以指定来源是相册还是相机，默认二者都有
            success: function (res) {
                console.log('wxChooseImage', res);
                var localIds = res.localIds; // 返回选定照片的本地ID列表，localId可以作为img标签的src属性显示图片
                window.imageId = localIds[0];
            }
        });
    }
    function testWxUploadImage() {
        window.jutouSdk.wxUploadImage({
            localId: window.imageId, // 需要上传的图片的本地ID，由chooseImage接口获得
            isShowProgressTips: 1, // 默认为1，显示进度提示
            success: function (res) {
                var serverId = res.serverId; // 返回图片的服务器端ID
                console.log(res);
            }
        });
    }
</script>
<button onclick="testWxChooseImage();">图片：wxChooseImage</button>
<br />
<button onclick="testWxUploadImage();">图片：wxUploadImage</button>
<br />

<br />

<script type="text/javascript">
    window.audioLocalId = null;
    // 停止录音
    function testNativeVoiceStopRecord() {
        window.jutouSdk.nativeVoiceStopRecord(function (res) {
            console.log('nativeVoiceStopRecord success', res);
            window.audioLocalId = res.localId;
        }, function (error) {
            console.log('nativeVoiceStopRecord error', error);
        });
    }
    // 播放录音
    function testNativeVoiceStartPlay() {
        console.log('播放录音');
        window.jutouSdk.nativeVoiceStartPlay(window.audioLocalId);
    }
</script>
<button onclick="window.jutouSdk.nativeVoiceStartRecord();">音频：开始录音</button>
<br />
<button onclick="testNativeVoiceStopRecord();">音频：停止录音</button>
<br />
<button onclick="testNativeVoiceStartPlay();">音频：播放录音</button>
<br />

<br />

<input type="file" accept="image/*" />
<br />

<br />

<button onclick="window.jutouSdk.nativePushRoute('webPage', { title:'我是新网页', url:'http://127.0.0.1:16800/jutou-sdk.php' })">测试 打开一个新网页</button>
<br />

<button onclick="window.jutouSdk.nativePushRoute('webPage', { title:'我是新网页', url:'http://127.0.0.1:16800/jutou-sdk.php', hideNavBar: true })">测试 打开一个新网页（没有Navbar）</button>
<br />

<button onclick="window.jutouSdk.nativePushRoute('webPage', { title:'我是新网页', url:'http://127.0.0.1:16800/jutou-sdk.php', hideNavBar: true, showBackButton: true })">测试 打开一个新网页（没有Navbar，原生返回按钮）</button>
<br />

<button onclick="window.jutouSdk.nativeChangeNavbarTitle('我是修改后的标题')">修改当前Navbar标题</button>
<br />

<br />

<script type="text/javascript">
    function nativeWechatLoginDemo() {
        window.jutouSdk.nativeWechatLogin('snsapi_userinfo', function (result, state) {
            console.log('nativeWechatLogin success', result, state);
        }, function (error) {
            console.log('nativeWechatLogin error', error);
        });
    }
</script>
<button onclick="nativeWechatLoginDemo()">微信登录,console看结果</button>
<br />

<script type="text/javascript">
    function nativeWechatShareToTimelineDemo() {
        window.jutouSdk.nativeWechatShareToTimeline({
            type: 'text',
            description: 'hello, wechat',
        }, function (result) {
            console.log('nativeWechatShareToTimeline success', result);
        }, function (error) {
            console.log('nativeWechatShareToTimeline error', error);
        });
    }
</script>
<button onclick="nativeWechatShareToTimelineDemo()">分享到微信朋友圈,console看结果</button>
<br />

<script type="text/javascript">
    function nativeWechatShareToSessionDemo() {
        window.jutouSdk.nativeWechatShareToSession({
            type: 'text',
            description: 'hello, wechat',
        }, function (result) {
            console.log('nativeWechatShareToSession success', result);
        }, function (error) {
            console.log('nativeWechatShareToSession error', error);
        });
    }
</script>
<button onclick="nativeWechatShareToSessionDemo()">分享给微信好友,console看结果</button>
<br />

<script type="text/javascript">
    function nativeWechatPayDemo() {
        window.jutouSdk.nativeWechatPay({
            appid: '',
            partnerId: '',
            prepayId: '',
            nonceStr: '',
            timeStamp: '',
            package: '',
            sign: '',
        }, function (result) {
            console.log('nativeWechatPay success', result);
        }, function (error) {
            console.log('nativeWechatPay error', error);
        });
    }
</script>
<button onclick="nativeWechatPayDemo()">调用微信支付,console看结果</button>
<br />

<br />

<script type="text/javascript">
    function nativeStorageLoadDemo() {
        window.jutouSdk.nativeStorageLoad('testKey', function (result) {
            console.log('nativeStorageLoad success', result);
        }, function (error) {
            console.log('nativeStorageLoad error', error);
        });
    }
</script>
<button onclick="nativeStorageLoadDemo()">读取存储,console看结果</button>
<br />

<script type="text/javascript">
    function nativeStorageRemoveDemo() {
        window.jutouSdk.nativeStorageRemove('testKey', function (result) {
            console.log('nativeStorageRemove success', result);
        }, function (error) {
            console.log('nativeStorageRemove error', error);
        });
    }
</script>
<button onclick="nativeStorageRemoveDemo()">删除存储,console看结果</button>
<br />

<script type="text/javascript">
    function nativeStorageSaveDemo() {
        window.jutouSdk.nativeStorageSave('testKey', { key: 'value', time: 1 }, function (result) {
            console.log('nativeStorageSave success', result);
        }, function (error) {
            console.log('nativeStorageSave error', error);
        });
    }
</script>
<button onclick="nativeStorageSaveDemo()">写入存储,console看结果</button>
<br />

<br />

<script type="text/javascript">
    function testNativeShowActionSheet() {
        var BUTTONS = ['Operation1', 'Operation2', 'Operation2', 'Delete', 'Cancel'];
        window.jutouSdk.nativeShowActionSheet({
            options: BUTTONS,
            cancelButtonIndex: BUTTONS.length - 1,
            destructiveButtonIndex: BUTTONS.length - 2,
            // title: 'title',
            message: 'I am description, description, description',
            maskClosable: true,
            'data-seed': 'logId'
        }, function (result) {
            console.log('nativeShowActionSheet', result);
        });
    }
    function testNativeShowShareActionSheet() {
        var dataList = [
            { url: 'https://gw.alipayobjects.com/zos/rmsportal/OpHiXAcYzmPQHcdlLFrc.png', title: '发送给朋友' },
            { url: 'https://gw.alipayobjects.com/zos/rmsportal/wvEzCMiDZjthhAOcwTOu.png', title: '新浪微博' },
            { url: 'https://gw.alipayobjects.com/zos/rmsportal/cTTayShKtEIdQVEMuiWt.png', title: '生活圈' },
            { url: 'https://gw.alipayobjects.com/zos/rmsportal/umnHwvEgSyQtXlZjNJTt.png', title: '微信好友' },
            { url: 'https://gw.alipayobjects.com/zos/rmsportal/SxpunpETIwdxNjcJamwB.png', title: 'QQ' }
        ];
        window.jutouSdk.nativeShowShareActionSheet({
            options: dataList,
            message: 'I am description, description, description'
        }, function (result) {
            console.log('nativeShowShareActionSheet', result);
        });
    }
</script>
<button onclick="testNativeShowActionSheet();">测试 nativeShowActionSheet</button>
<button onclick="testNativeShowShareActionSheet();">测试 nativeShowShareActionSheet</button>
<br />

<br />

<button onclick="testOpenDocument();">测试 openDocument</button>
<script type="text/javascript">
    function testOpenDocument() {
        jutouSdk.openDocument({
            filePath: 'https://www.edb.gov.hk/attachment/tc/curriculum-development/kla/chi-edu/second-lang/ModernCL.pdf'
        });
    }
</script>

<br />
<br />


