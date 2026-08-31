-- Phase 0 probe. Proves an .mpackage ships plain .lua files into the profile
-- VFS and that require() finds them there. Deleted once the split lands.

return {
    where = debug.getinfo(1, 'S').source,
    -- Errors raised inside a required file should name the file and the line,
    -- which is the half of this that neither concatenation nor one script node
    -- per file can give.
    boom = function() error('deliberate') end,
}
