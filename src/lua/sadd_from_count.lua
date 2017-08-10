local key = KEYS[1]
local num = KEYS[2]
for i=1,num
do
    redis.call('sadd',key,10000000+i)
end
return 1