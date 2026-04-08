<template>
  <div class="plans-container">
    <div class="page-header">
      <h2>订阅套餐管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        新增套餐
      </el-button>
    </div>

    <el-table :data="plans" v-loading="loading" stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="套餐名称" min-width="150" />
      <el-table-column prop="code" label="套餐代码" width="120" />
      <el-table-column prop="price" label="价格" width="100">
        <template #default="{ row }">
          ¥{{ row.price }}
        </template>
      </el-table-column>
      <el-table-column prop="billing_cycle" label="计费周期" width="100">
        <template #default="{ row }">
          <el-tag :type="getCycleTagType(row.billing_cycle)">
            {{ getCycleLabel(row.billing_cycle) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="included_usage" label="包含用量" min-width="200">
        <template #default="{ row }">
          <div v-if="row.included_usage && Object.keys(row.included_usage).length > 0">
            <el-tag v-for="(value, key) in row.included_usage" :key="key" size="small" class="usage-tag">
              {{ getDimensionName(key) }}: {{ value }}
            </el-tag>
          </div>
          <span v-else class="text-muted">无</span>
        </template>
      </el-table-column>
      <el-table-column prop="is_active" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'">
            {{ row.is_active ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
          <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑套餐' : '新增套餐'"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="planFormRef"
        :model="planForm"
        :rules="planRules"
        label-width="100px"
      >
        <el-form-item label="套餐名称" prop="name">
          <el-input v-model="planForm.name" placeholder="请输入套餐名称" />
        </el-form-item>
        <el-form-item label="套餐代码" prop="code">
          <el-input v-model="planForm.code" placeholder="请输入套餐代码" :disabled="isEdit" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input
            v-model="planForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入套餐描述"
          />
        </el-form-item>
        <el-form-item label="价格" prop="price">
          <el-input-number
            v-model="planForm.price"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="计费周期" prop="billing_cycle">
          <el-select v-model="planForm.billing_cycle" placeholder="请选择计费周期" style="width: 100%">
            <el-option label="月付" value="monthly" />
            <el-option label="季付" value="quarterly" />
            <el-option label="年付" value="yearly" />
          </el-select>
        </el-form-item>
        <el-form-item label="功能特性" prop="features">
          <el-select
            v-model="planForm.features"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="请输入功能特性，按回车添加"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="包含用量">
          <div class="usage-section">
            <div v-for="(item, index) in planForm.included_usage_list" :key="index" class="usage-item">
              <el-select
                v-model="item.dimension_code"
                placeholder="选择计量维度"
                style="width: 180px"
                @change="handleDimensionChange(index)"
              >
                <el-option
                  v-for="dim in availableDimensions"
                  :key="dim.code"
                  :label="dim.name"
                  :value="dim.code"
                />
              </el-select>
              <el-input-number
                v-model="item.limit"
                :min="0"
                placeholder="用量上限"
                style="width: 150px; margin-left: 10px"
              />
              <el-button type="danger" link @click="removeUsageItem(index)" style="margin-left: 10px">
                删除
              </el-button>
            </div>
            <el-button type="primary" link @click="addUsageItem">
              <el-icon><Plus /></el-icon>
              添加用量
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="状态" prop="is_active">
          <el-switch v-model="planForm.is_active" active-text="启用" inactive-text="禁用" />
        </el-form-item>
        <el-form-item label="排序" prop="sort_order">
          <el-input-number v-model="planForm.sort_order" :min="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getPlans, createPlan, updatePlan, getDimensions } from '@/api/plan'

const loading = ref(false)
const submitLoading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const plans = ref([])
const dimensions = ref([])

const planFormRef = ref(null)

const planForm = reactive({
  name: '',
  code: '',
  description: '',
  price: 0,
  billing_cycle: 'monthly',
  features: [],
  included_usage_list: [],
  is_active: true,
  sort_order: 0
})

const planRules = {
  name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入套餐代码', trigger: 'blur' }],
  price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
  billing_cycle: [{ required: true, message: '请选择计费周期', trigger: 'change' }]
}

const availableDimensions = computed(() => {
  return dimensions.value || []
})

const getCycleLabel = (cycle) => {
  const labels = {
    monthly: '月付',
    quarterly: '季付',
    yearly: '年付'
  }
  return labels[cycle] || cycle
}

const getCycleTagType = (cycle) => {
  const types = {
    monthly: 'primary',
    quarterly: 'success',
    yearly: 'warning'
  }
  return types[cycle] || 'info'
}

const getDimensionName = (code) => {
  const dim = dimensions.value.find(d => d.code === code)
  return dim ? dim.name : code
}

const fetchPlans = async () => {
  loading.value = true
  try {
    const res = await getPlans()
    plans.value = res.data || []
  } catch (error) {
    console.error('获取套餐列表失败:', error)
  } finally {
    loading.value = false
  }
}

const fetchDimensions = async () => {
  try {
    const res = await getDimensions()
    dimensions.value = res.data || []
  } catch (error) {
    console.error('获取计量维度失败:', error)
  }
}

const resetForm = () => {
  planForm.name = ''
  planForm.code = ''
  planForm.description = ''
  planForm.price = 0
  planForm.billing_cycle = 'monthly'
  planForm.features = []
  planForm.included_usage_list = []
  planForm.is_active = true
  planForm.sort_order = 0
}

const handleAdd = () => {
  isEdit.value = false
  resetForm()
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  resetForm()
  
  planForm.name = row.name
  planForm.code = row.code
  planForm.description = row.description || ''
  planForm.price = parseFloat(row.price)
  planForm.billing_cycle = row.billing_cycle
  planForm.features = row.features || []
  planForm.is_active = row.is_active
  planForm.sort_order = row.sort_order || 0
  
  if (row.included_usage && Object.keys(row.included_usage).length > 0) {
    planForm.included_usage_list = Object.entries(row.included_usage).map(([code, limit]) => ({
      dimension_code: code,
      limit: limit
    }))
  }
  
  dialogVisible.value = true
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除套餐"${row.name}"吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      ElMessage.success('删除成功')
      fetchPlans()
    } catch (error) {
      console.error('删除失败:', error)
    }
  }).catch(() => {})
}

const addUsageItem = () => {
  planForm.included_usage_list.push({
    dimension_code: '',
    limit: 0
  })
}

const removeUsageItem = (index) => {
  planForm.included_usage_list.splice(index, 1)
}

const handleDimensionChange = (index) => {
}

const handleSubmit = async () => {
  const valid = await planFormRef.value.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  
  const submitData = {
    name: planForm.name,
    code: planForm.code,
    description: planForm.description,
    price: planForm.price,
    billing_cycle: planForm.billing_cycle,
    features: planForm.features,
    is_active: planForm.is_active,
    sort_order: planForm.sort_order
  }
  
  if (planForm.included_usage_list.length > 0) {
    submitData.included_usage = {}
    planForm.included_usage_list.forEach(item => {
      if (item.dimension_code) {
        submitData.included_usage[item.dimension_code] = item.limit
      }
    })
  }

  try {
    if (isEdit.value) {
      const plan = plans.value.find(p => p.code === planForm.code)
      if (plan) {
        await updatePlan(plan.id, submitData)
        ElMessage.success('更新成功')
      }
    } else {
      await createPlan(submitData)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    fetchPlans()
  } catch (error) {
    console.error('提交失败:', error)
  } finally {
    submitLoading.value = false
  }
}

onMounted(() => {
  fetchPlans()
  fetchDimensions()
})
</script>

<style scoped>
.plans-container {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.usage-tag {
  margin-right: 5px;
  margin-bottom: 5px;
}

.text-muted {
  color: #909399;
}

.usage-section {
  width: 100%;
}

.usage-item {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}
</style>
