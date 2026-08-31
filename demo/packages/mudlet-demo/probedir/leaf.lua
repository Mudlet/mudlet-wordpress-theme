-- Phase 0 probe: a module one directory down, required by its own init.lua.

return { where = debug.getinfo(1, 'S').source }
