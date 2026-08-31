-- Phase 0 probe, through package.path's second pattern (`?/init.lua`) — what
-- rooms/init.lua will be — and requiring a sibling inside its own directory.

local leaf = require('mudlet-demo.probedir.leaf')

return {
    where = debug.getinfo(1, 'S').source,
    leaf = leaf.where,
}
