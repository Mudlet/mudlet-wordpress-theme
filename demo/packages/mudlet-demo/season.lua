-- The calendar ------------------------------------------------------------------
--
-- What day it is where the visitor is sitting, which is the one fact in this
-- world that is neither typed nor asked of the site: the clock is the
-- visitor's, and so is the date the furniture turns up on. A thing with a
-- `when` in its room is only there while that answers true — see present() in
-- verbs.lua — so a seasonal visitor is a line in a room, and on every other day
-- of the year the room is exactly what it was.
--
-- `lua demo.today = '10-31'` pretends, for whoever wants to see a day early;
-- `lua demo.today = nil` stops. Asked for through pcall like the clerk's clock
-- in github.lua: a wasm with less of the os library than it claims costs the
-- decoration, not the room.

local D = demo

local function today()
    if D.today then return D.today end
    local ok, s = pcall(os.date, '%m-%d')
    return ok and type(s) == 'string' and s or ''
end

local function halloween() return today() == '10-31' end

return { today = today, halloween = halloween }
