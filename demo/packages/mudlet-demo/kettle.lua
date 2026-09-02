-- The third direction: nobody typing ------------------------------------------
--
-- mudlet-demo/trigger.lua gives the visitor a trigger, which is the client
-- reacting to what the world says. mudlet-demo/catalogue.lua gives them an
-- alias, which is the client reshaping what the visitor says. Both of those
-- still begin with somebody at the keyboard, and between them they leave out the
-- half of Mudlet that runs when nobody is: a timer.
--
-- Every scripted thing in this package is already one — the sage waits two
-- seconds before greeting you, the clerk's fetch gives up after four, the whole
-- connect animation is a repeating tempTimer — and none of it is ever handed
-- over. So the Workshop has a kettle. Put it on, walk two rooms away, and it
-- finds you: the line arrives in the Vault, or in the Stacks, because the timer
-- belongs to the client and not to the room it was started in. That is the
-- entire lesson and it cannot be told, only left running.
--
-- Two details are load-bearing.
--
-- The click goes out through dfeedTriggers, for the same reason the clerk's coin
-- does. A timer firing is the nearest this offline world comes to a server
-- sending something unbidden, and a line that arrives unbidden should arrive the
-- way a game's line arrives — through the trigger engine, where the visitor's own
-- pattern gets a look at it. The word `kettle` is in that line deliberately: a
-- visitor who took the clerk's job first and wrote `trigger on kettle` sees the
-- two lessons compose two rooms away, one thing they made firing on another
-- thing they started, and nothing in either file arranged it.
--
-- And the line says where the visitor was standing when it landed. Not
-- decoration: "you are in Makers Hall and it found you anyway" is the proof, and
-- the world can only say it because it looked at D.here at the moment the timer
-- fired rather than at the moment it was set.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, cmd, link = core.C, core.say, core.cmd, core.link

-- One constant, for the same reason trigger.lua keeps one word: the number
-- appears in the prose, in the Lua the visitor is offered, and in the call the
-- shortcut makes, and a demonstration whose three halves disagree teaches the
-- wrong lesson.
--
-- Fifteen seconds because the point is the walking. Long enough to leave the
-- Workshop, go through the commons and be somewhere else entirely when it goes
-- off; short enough that a visitor who stands and waits has not been abandoned.
local SECONDS = 15

-- The call the kettle puts up, and — near enough — the one D.boil() makes for
-- anybody who would rather not read Lua. Written once, so what is shown and what
-- is run cannot drift apart in the number that matters.
local function luaFor(s)
    return string.format(
        'lua tempTimer(%d, function() cecho("\\n<gold>The kettle clicks off.\\n") end)', s)
end
local LUA = luaFor(SECONDS)

-- The visitor's timer. Kept so a second putting-on replaces the first rather
-- than stacking, and so taking it off has something to kill.
local id

-- Mudlet knows how long is left and will say so; older builds may not have the
-- call at all, and a kettle is not worth a hard dependency. Nil means "do not
-- claim to know".
local function left()
    if not id or type(remainingTime) ~= 'function' then return nil end
    local ok, secs = pcall(remainingTime, id)
    if not ok or type(secs) ~= 'number' or secs < 0 then return nil end
    return math.floor(secs + 0.5)
end

-- What the visitor sees when the timer fires.
--
-- dfeedTriggers and not say(): see the header. The line is handed to the engine
-- as though a server had sent it, which is what makes it catchable.
local function clicked()
    id = nil

    dfeedTriggers(C.text .. 'Somewhere behind you a kettle reaches the boil, '
        .. 'rattles its lid twice, and clicks itself off.\n')

    -- Read now, not when the timer was set. Where the visitor happens to be
    -- standing fifteen seconds later is the whole demonstration.
    if D.here == 'workshop' then
        say(C.dim, 'You are standing next to it, which rather wastes the trick. Put it ',
            'on again and ', cmd('walk off', 'south', 'go south, and keep going', C.dim),
            C.dim, ' — the timer is the client\'s, not this room\'s, and it will ',
            'follow you anywhere in the world.')
    else
        say(C.dim, 'You are in ', D.rooms[D.here].title, ', and it found you anyway. ',
            'Nothing in this room knew about a kettle. The timer was never the ',
            'Workshop\'s — it is the client\'s, and the client is the same one ',
            'wherever you walk to.')
    end
    say(C.dim, 'That is the third of them. A trigger fires on what the world says, ',
        'an alias on what you say, and a timer on nobody saying anything at all.')
end

-- Put it on.
function D.boil()
    if id then
        local secs = left()
        say(C.text, 'It is already on', secs and (', about ' .. core.spell(secs)
            .. ' seconds off the boil') or '', '.')
        if secs then
            say(C.dim, '  remainingTime(' .. tostring(id) .. ') -> ' .. tostring(secs))
        end
        say(C.dim, 'You could ', cmd('take it off', 'kettle off', 'killTimer', C.dim),
            C.dim, ' instead.')
        return
    end

    id = tempTimer(SECONDS, clicked)

    say()
    say(C.text, 'You fill it at the tap in the corner and press the switch down. It ',
        'begins to tick.')
    -- The Lua the shortcut just stood in for, so even the lazy path shows its
    -- working — the same bargain `trigger on gold` and `alias b` strike.
    say(C.dim, '  ', luaFor(SECONDS))
    say(C.dim, '  -> timer ', tostring(id), '   ·   ',
        cmd('kettle off', 'kettle off', 'kill it again', C.dim))
    say()
    say(C.dim, 'Now leave. Go ', cmd('south', 'south', 'go south', C.dim),
        C.dim, ', go anywhere — it will find you in ', core.spell(SECONDS),
        ' seconds, and that is the point of it.')
end

-- Take it off again.
function D.kettleOff(quiet)
    if not id then
        if not quiet then
            say(C.text, 'The kettle is cold and the switch is already up.')
        end
        return
    end

    killTimer(id)
    id = nil
    if quiet then return end
    say(C.text, 'You lift the switch. The ticking stops, and nothing will happen ',
        'now — which is the other half of a timer having an id.')
    say(C.dim, '  killTimer(id)')
end

-- Pressing it is the physical version of both verbs, so it toggles: a kettle
-- that is on and pressed goes off.
local function grab()
    if id then D.kettleOff() else D.boil() end
end

function D.lookKettle()
    say(C.text, 'A cheap plastic kettle at the end of the bench, furred inside, with ',
        'one mug beside it that has been rinsed rather than washed. The coffee in ',
        'this room is yesterday\'s. This is how it stops being.')
    say(C.dim, 'Everything else here waits on somebody typing. This does not: put it ',
        'on, walk away, and it will interrupt you wherever you have got to.')
    say()
    say(C.dim, '  ', cmd(LUA, LUA, 'set a real timer in this client', C.exit))
    say()
    say(C.dim, 'That is the whole of it — ', link('tempTimer', URL.timers), C.dim,
        ', a number of seconds, and something to do when they are up. Click it, ',
        'or ', cmd('put the kettle on', 'put the kettle on',
            'the same timer, with the world watching it', C.dim),
        C.dim, ' and let the world tell you where you were standing.')
end

return { SECONDS = SECONDS, grab = grab }
