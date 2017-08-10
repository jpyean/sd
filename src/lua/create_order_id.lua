local time = KEYS[1]
local order_incr = 'order_id_incr_'..time
local order_incr_id = 10000+redis.call('incr',order_incr)
local order_id = time..order_incr_id
redis.call('expire',order_incr,1)
return order_id