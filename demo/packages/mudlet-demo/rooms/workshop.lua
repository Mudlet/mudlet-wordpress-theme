-- The Workshop, north of the commons. The clerk who stands in it is the one
-- thing in this world that does not know what it says until somebody asks —
-- the machinery, and the reasoning, are in mudlet-demo/github.lua.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd

return {
    title = 'The Workshop',
    desc = 'Long, high-windowed, and smelling of solder and yesterday\'s coffee. Down '
        .. 'one wall a bench of work half-done and carefully labelled; down the other, '
        .. 'a board of everything nobody has got to yet, which is a good deal longer. '
        .. 'By the window stands a slanted desk with this week\'s date on it, and a '
        .. 'clerk keeping the book open at that page.',
    exits = { south = 'commons' },
    things = {
        {
            name = 'the clerk',
            keys = { 'clerk', 'bookkeeper' },
            npc = true,
            presence = function()
                say(C.text, 'A ', cmd('clerk', 'look clerk', 'look at the clerk', C.say),
                    C.text, ' stands at the slanted desk, one pen behind the ear, writing ',
                    'up the week.')
            end,
            look = function()
                say(C.text, 'Sleeves rolled, one pen behind the ear and a second one in use. ',
                    'The clerk keeps the week — what has landed, who landed it, and what is ',
                    'still open — and writes none of it down until somebody asks, which ',
                    'they maintain is the only way to keep a book honest.')
                say(C.dim, 'Ask ', cmd('about this week', 'ask about this week',
                        'what has landed in the last seven days', C.dim),
                    C.dim, ', or ', cmd('about what is open', 'ask about issues',
                        'what is still open', C.dim), C.dim, '.')
                say(C.dim, 'Or ', cmd('ask about the job', 'ask about work',
                        'the one thing here that scripts the client', C.dim),
                    C.dim, ', which is the only work going.')
                say(C.dim, 'Both answers come off github.com at the moment you ask for them. ',
                    'Nothing else in this world is that fresh.')
            end,
        },
        {
            name = 'the week\'s book',
            keys = { 'book', 'week', 'desk', 'ledger', 'commits' },
            look = function() D.week() end,
        },
        {
            name = 'the board',
            keys = { 'board', 'issues', 'bugs', 'wall' },
            url = URL.issues,
            look = function() D.issues() end,
        },
        {
            name = 'the bench',
            keys = { 'bench', 'pulls', 'patches', 'work' },
            url = URL.pulls,
            look = function()
                say(C.text, 'Work half-done, each piece labelled with the name of whoever put ',
                    'it down and the date they meant to come back to it. Some of the labels ',
                    'are old. All of it is out in the open, which is the whole difference ',
                    'between this bench and most benches.')
                say(C.dim, '  ', link('the open pull requests', URL.pulls))
            end,
        },
    },
}
