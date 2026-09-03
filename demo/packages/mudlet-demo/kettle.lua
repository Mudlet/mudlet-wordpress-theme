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

-- One constant, for the same reason trigger.lua keeps one word: the number is
-- in the prose the room says and in the call the switch makes, and a
-- demonstration whose two halves disagree teaches the wrong lesson.
--
-- Fifteen seconds because the point is the walking. Long enough to leave the
-- Workshop, go through the commons and be somewhere else entirely when it goes
-- off; short enough that a visitor who stands and waits has not been abandoned.
local SECONDS = 15

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
        say(C.dim, 'You could ', cmd('take it off', 'kettle off', 'killTimer', C.dim),
            C.dim, ' instead.')
        return
    end

    id = tempTimer(SECONDS, clicked)

    say()
    say(C.text, 'You fill it at the tap in the corner and press the switch down. It ',
        'begins to tick.')
    -- No echo of the call it just made, and none anywhere else in this file.
    -- The Stacks and the Workshop print their Lua because in those two rooms
    -- the Lua *is* what the visitor came for — an imp handing over a box with
    -- an alias on the lid, a bench meant for working at. A kettle is a kettle:
    -- the lesson is the line that arrives two rooms away, and source under the
    -- switch is the console talking over it. `look kettle` names tempTimer and
    -- links the manual, which is as much as a kettle owes anybody.
    say(C.dim, '  ', cmd('kettle off', 'kettle off', 'lift the switch again', C.dim))
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
    say(C.dim, 'It is a ', link('tempTimer', URL.timers), C.dim, ' and nothing more — ',
        'a number of seconds, and something to do when they are up. ',
        cmd('Put it on', 'put the kettle on', 'fifteen seconds, starting now', C.dim),
        C.dim, ' and let the world tell you where you were standing.')
end

return { SECONDS = SECONDS, grab = grab }
