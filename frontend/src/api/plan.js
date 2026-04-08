import request from '@/utils/request'

export function getPlans() {
  return request({
    url: '/admin/plans',
    method: 'get'
  })
}

export function createPlan(data) {
  return request({
    url: '/admin/plans',
    method: 'post',
    data
  })
}

export function updatePlan(id, data) {
  return request({
    url: `/admin/plans/${id}`,
    method: 'put',
    data
  })
}

export function getDimensions() {
  return request({
    url: '/admin/metering-dimensions',
    method: 'get'
  })
}
