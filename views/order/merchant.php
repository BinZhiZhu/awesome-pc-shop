<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);

?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>

</head>
<style>
    .el-row {
        margin-bottom: 20px;

    &
    :last-child {
        margin-bottom: 0;
    }

    }
    .el-col {
        border-radius: 4px;
    }

    .bg-purple-dark {
        background: #99a9bf;
    }

    .bg-purple {
        background: #d3dce6;
    }

    .bg-purple-light {
        background: #e5e9f2;
    }

    .grid-content {
        text-align: center;
        border-radius: 4px;
        min-height: 40px;
        padding: 10px;
        color: white;
        font-size: 18px;
    }

    .row-bg {
        padding: 10px 0;
        background-color: #f9fafc;
    }

    .block {
        display: flex;
        flex-direction: row;
        justify-content: center;
        padding-top: 20px;
    }

    .tab {
        padding: 20px;
    }

    .el-dialog {
        margin-top: 10px !important;
    }
</style>
<?php $this->beginBody() ?>
<div id="app">
    <div class="tab">
        <el-breadcrumb separator-class="el-icon-arrow-right">
            <el-breadcrumb-item :to="{ path: '/' }">订单管理</el-breadcrumb-item>
            <el-breadcrumb-item>订单列表</el-breadcrumb-item>
        </el-breadcrumb>
    </div>
    <template>
        <!--        <el-row>-->
        <!--            <el-col :span="24"><div class="grid-content bg-purple-dark">后台用户列表</div></el-col>-->
        <!--        </el-row>-->
        <el-table
                :data="tableData"
                border="true"
                style="width: 100%">
            <el-table-column
                    label="编号"
                    align="center"
                    width="100">
                <template slot-scope="scope">{{scope.row.id}}</template>
            </el-table-column>
            <el-table-column
                    label="订单号"
                    align="center"
                    width="150">
                <template slot-scope="scope">{{scope.row.order_sn}}</template>
            </el-table-column>
            <el-table-column
                    label="商品名称"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.title}}</template>
            </el-table-column>
            <el-table-column
                    label="订单价格(单位/元)"
                    align="center"
                    width="150">
                <template slot-scope="scope">{{scope.row.order_price}}</template>
            </el-table-column>
            <el-table-column
                    label="订单数量(单位/件)"
                    align="center"
                    width="150">
                <template slot-scope="scope">{{scope.row.total}}</template>
            </el-table-column>
            <el-table-column
                    label="下单时间"
                    align="center"
            >
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.created_at }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="操作"
                    align="center"
            >
                <template slot-scope="scope">
                    <el-button
                            size="mini"
                            type="danger"
                            @click="handleDelete(scope.$index, scope.row)">删除
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </template>
    <div class="block">
        <el-pagination
                @prev-click="prev_click"
                @next-click="next_click"
                :page-size="10"
                :pager-count="11"
                layout="prev, pager, next"
                :total="total">
        </el-pagination>
    </div>
</div>
<script>
    axios.defaults.baseURL = "";
    const router = new VueRouter({
        routes: [
            {path: '/site', name: 'indexPage'},
        ]
    });

    new Vue({
        el: '#app',
        router,
        data() {
            return {
                page: 1,
                total: "",
                formLabelWidth: '120px',
                tableData: [
                    {
                        id: 0,
                        lastvisit_ip: "",
                        lastvisit_time: "",
                        login_count: "4",
                        password: "",
                        register_ip: "",
                        register_time: "",
                        role: "",
                        salt: "",
                        status: "",
                        username: "",
                    }
                ]
            };
        },
        created: function () {
            this.getOrderList()
        },
        methods: {
            getOrderList() {
                let url = '<?php echo \yii\helpers\Url::toRoute('order/get-merchant-order-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('获取商家订单列表结果', resp);
                        this.tableData = resp.result.list
                        this.total = resp.result.total
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            prev_click() {
                var page = parseInt(app.page) - 1;
                let url = '<?php echo \yii\helpers\Url::toRoute('backend/get-user-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.tableData = resp.result.list
                        this.total = resp.result.total
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            next_click() {
                var page = parseInt(app.page) + 1;
                let url = '<?php echo \yii\helpers\Url::toRoute('backend/get-user-list');?>';
                axios.post(url)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.tableData = resp.result.list
                        this.total = resp.result.total
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            current_change(e) {
            },
            alertMessage(msg, close, type) {
                this.$message({
                    showClose: close,
                    message: msg,
                    type: type
                });
            },
            handleDelete(index, row) {
                console.log(index, row);
                let url = '<?php echo \yii\helpers\Url::toRoute('order/delete-order');?>';
                let param = new URLSearchParams();
                const postdata = {
                    id: row.id
                }
                param.append('id', postdata.id);
                console.log('--删除接口--', param)
                axios.post(url, param)
                    .then(response => {
                        const resp = response.data;
                        console.log('success', resp);
                        this.getOrderList()
                        this.alertMessage(resp.message, true, resp.code === 200 ? 'success' : 'error')
                    })
                    .catch(error => {
                        console.log(error)

                    });
            }
        },
    });
</script>

<?php $this->endBody() ?>
</html>
<?php $this->endPage() ?>
