local couponTable = KEYS[1]
local order_phase_coupon_table = KEYS[2]
local is_exist = redis.call('exists',order_phase_coupon_table)
if (is_exist==0)
then
    return 0
end
redis.call('sUnionStore',couponTable,order_phase_coupon_table,couponTable)
redis.call('del',order_phase_coupon_table)
return 1