<template>
  <div class="container">
    <h1>📝 我的待办事项</h1>

    <!-- 输入框区域 -->
    <div class="input-group">
      <input
        v-model="newTaskTitle"
        @keyup.enter="addTask"
        type="text"
        placeholder="输入新任务..."
      />
      <button @click="addTask">添加</button>
    </div>

    <!-- 列表区域 -->
    <ul class="task-list">
      <li v-for="task in tasks" :key="task.id" class="task-item">
        <div class="task-content" @click="toggleStatus(task)">
          <!-- 根据 status 显示不同的图标 -->
          <span class="icon">{{ task.status === 1 ? '✅' : '⬜' }}</span>
          <!-- 根据 status 决定是否划掉文字 -->
          <span :class="{ 'done': task.status === 1 }">{{ task.title }}</span>
        </div>
        <button class="del-btn" @click="deleteTask(task.id)">删除</button>
      </li>
    </ul>

    <p v-if="loading" class="loading">加载中...</p >
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// ========== API 自动切换配置 ==========
const REMOTE_API = 'https://vue-api-277353-9-1449749052.sh.run.tcloudbaseapp.com/api.php';  // CloudBase 远程
const LOCAL_API = 'http://localhost:8080/api.php';                      // 本地备用

// 当前使用的 API 地址
const currentApi = ref(REMOTE_API);
const tasks = ref([]);
const newTaskTitle = ref('');
const loading = ref(false);

// ========== 智能请求函数：优先远程，失败自动切换本地 ==========
async function smartFetch(url, options = {}) {
  // 设置10秒超时（给 Railway 更多响应时间）
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);

  try {
    // 优先尝试当前配置的地址
    const res = await fetch(url, {
      ...options,
      signal: controller.signal
    });
    clearTimeout(timeout);
    return res;
  } catch (error) {
    clearTimeout(timeout);

    // 如果当前用的是远程，失败了就切换到本地
    if (currentApi.value === REMOTE_API) {
      console.log('Railway 连接失败，切换到本地...');
      currentApi.value = LOCAL_API;
      const localUrl = url.replace(REMOTE_API, LOCAL_API);
      return smartFetch(localUrl, options); // 递归重试本地
    }

    // 本地也失败了，直接抛出错误
    throw error;
  }
}

// 统一 API 调用（自动切换）
async function apiCall(action, method = 'GET', body = null) {
  const url = `${currentApi.value}?action=${action}`;
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  if (body) {
    options.body = JSON.stringify(body);
  }
  return smartFetch(url, options);
}

// ========== 业务逻辑 ==========

// 1. 获取列表
const fetchTasks = async () => {
  loading.value = true;
  try {
    const res = await apiCall('list');
    const data = await res.json();
    tasks.value = data;
  } catch (e) {
    console.error("获取失败", e);
  } finally {
    loading.value = false;
  }
};

// 2. 添加任务
const addTask = async () => {
  if (!newTaskTitle.value.trim()) return;

  try {
    await apiCall('add', 'POST', { title: newTaskTitle.value });
    newTaskTitle.value = '';
    fetchTasks();
  } catch (e) {
    console.error("添加失败", e);
    alert("添加出错：请确保本地 PHP 后端已启动 (php -S localhost:8080)");
  }
};

// 3. 切换完成状态
const toggleStatus = async (task) => {
  const newStatus = task.status === 1 ? 0 : 1;
  task.status = newStatus;

  try {
    await apiCall('update', 'POST', { id: task.id, status: newStatus });
  } catch (e) {
    console.error("更新失败", e);
  }
};

// 4. 删除任务
const deleteTask = async (id) => {
  if (!confirm("确定删除吗？")) return;

  try {
    await apiCall('delete', 'POST', { id });
    fetchTasks();
  } catch (e) {
    console.error("删除失败", e);
  }
};

// 页面加载时获取数据
onMounted(() => {
  fetchTasks();
});
</script>

<style scoped>
.container {
  max-width: 500px;
  margin: 50px auto;
  font-family: sans-serif;
  padding: 20px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  border-radius: 8px;
}

.input-group {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

button {
  padding: 10px 20px;
  background-color: #42b983;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.task-list {
  list-style: none;
  padding: 0;
}

.task-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-bottom: 1px solid #eee;
}

.task-content {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  flex: 1;
}

.done {
  text-decoration: line-through;
  color: #888;
}

.del-btn {
  background-color: #ff4d4f;
  padding: 5px 10px;
  font-size: 12px;
}

.loading {
  text-align: center;
  color: #666
  ;
}
</style>
