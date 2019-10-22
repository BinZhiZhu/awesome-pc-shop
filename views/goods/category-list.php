<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
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
    <!--    <link rel="shortcut icon" href="--><? //=$host  ?><!--/favicon.ico" type="image/x-icon"/>-->
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

    .avatar-uploader .el-upload {
        width: 178px;
        height: 178px;
        border: 1px dashed #d9d9d9;
        border-radius: 6px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .avatar-uploader .el-upload:hover {
        border-color: #409EFF;
    }

    .avatar-uploader-icon {
        font-size: 28px;
        color: #8c939d;
        width: 178px;
        height: 178px;
        line-height: 178px;
        text-align: center;
    }

    .avatar {
        width: 178px;
        height: 178px
        display: block;
    }
</style>
<?php $this->beginBody() ?>
<div id="app">
    <div class="tab">
        <el-breadcrumb separator-class="el-icon-arrow-right">
            <el-breadcrumb-item :to="{ path: '/' }">商品管理</el-breadcrumb-item>
            <el-breadcrumb-item>商品分类列表</el-breadcrumb-item>
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
                    label="标题"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.title}}</template>
            </el-table-column>
            <el-table-column
                    label="创建人"
                    align="center"
                    width="180">
                <template slot-scope="scope">{{scope.row.created_by}}</template>
            </el-table-column>
            <el-table-column
                    label="发布时间"
                    align="center"
                    width="180">
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.created_at }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="更新时间"
                    align="center"
                    width="180">
                <template slot-scope="scope">
                    <i class="el-icon-time"></i>
                    <span style="margin-left: 10px">{{ scope.row.updated_at }}</span>
                </template>
            </el-table-column>
            <el-table-column
                    label="操作"
                    align="center"
            >
                <template slot-scope="scope">
                    <el-button
                            size="mini"
                            @click="handleEdit(scope.$index, scope.row)">编辑
                    </el-button>
                    <el-button
                            size="mini"
                            type="danger"
                            @click="handleDelete(scope.$index, scope.row)">删除
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-drawer
                title="分类信息"
                size="50%"
                :visible.sync="drawer"
                :direction="direction"
        >
            <el-form :label-position="labelPosition" label-width="100px" :model="ruleForm" :rules="rules"
                     ref="ruleForm">
                <el-form-item label="分类名称" prop="title">
                    <el-input v-model="ruleForm.title"></el-input>
                </el-form-item>
                <el-form-item align="center">
                    <el-button type="primary" @click="submitForm('ruleForm')">保存分类</el-button>
                    <el-button @click="resetForm('ruleForm')">重置</el-button>
                </el-form-item>
            </el-form>
        </el-drawer>

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
                drawer: false,
                direction: 'rtl',
                formLabelWidth: '120px',
                category: {},
                tableData: [
                    {
                        id: 0,
                        created_at: "",
                        title: '',
                    }
                ],
                category_id: 0,
                ruleForm: {
                    id: 0,
                    created_at: "",
                    title: '',
                },
                rules: {
                    title: [
                        {required: true, message: '请输入分类名称', trigger: 'blur'},
                    ]
                },
                labelPosition: 'right',
            };
        },
        created: function () {
            // `this` 指向 vm 实例
            this.getCategoryList()
        },
        methods: {
            getCategoryList() {
                let url = '<?php echo \yii\helpers\Url::toRoute('goods/get-category-list');?>';
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
            handleEdit(index, row) {
                console.log(index, row);
                this.drawer = true
                this.ruleForm = row
                this.category_id = row.id
            },
            submitForm(formName) {
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        const postData = this.ruleForm
                        console.log('postData', postData)
                        let url = '<?php echo \yii\helpers\Url::toRoute('goods/edit-category');?>';
                        let param = new URLSearchParams();
                        param.append('title', postData.title);
                        param.append('category_id', this.category_id);
                        axios.post(url, param)
                            .then(response => {
                                console.log('获取编辑分类结果', response.data);
                                if (response.data.code === 200) {
                                    const resp = response.data.result;
                                    this.$notify({
                                        title: response.data.message,
                                        message: '',
                                        type: 'success',
                                    });
                                    this.drawer = false
                                } else {
                                    this.alertMessage(response.data.message, true, 'error');
                                }
                            })
                            .catch(error => {
                                console.log(error)
                            });
                    } else {
                        console.log('error submit!!');
                        return false;
                    }
                });
            },
            //重置表单 移除校验结果
            resetForm(formName) {
                this.$refs[formName].resetFields();
            },
            handleDelete(index, row) {
                console.log(index, row);
                let url = '<?php echo \yii\helpers\Url::toRoute('goods/delete-category');?>';
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
                        this.getCategoryList()
                        if (resp.code === 200) {
                            this.alertMessage(resp.message, true, 'success')
                        } else {
                            this.alertMessage(resp.message, true, 'error')
                        }
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
