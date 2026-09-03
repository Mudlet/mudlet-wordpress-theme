-- What this world is made of ---------------------------------------------------
--
-- The one generated fact in the package. scripts/build-package.mjs walks the
-- .lua files it is about to zip, counts the lines in each, and writes the lot
-- into the single line below — one line of table literal in place of one line
-- of empty table, so the counts still describe the files they were counted
-- from, this file included.
--
-- Two rooms read it. The terminal on the plinth in the front room quotes the
-- total at whoever leans over it, and the cellar under the Release Vault is
-- shelves of the crates themselves. Neither can drift from what shipped,
-- because neither is typed: a line count written into prose is wrong at the
-- next edit, including the edit that changes the prose.
--
-- Each entry is { path, lines, comment, code, shipped }. Comment and code are
-- counted apart because the cellar makes a joke of the ratio, and the crate
-- that wins it is too small to be on the shelf otherwise; blank lines are the
-- three of them subtracted. `shipped` is false for exactly one file: embed.lua
-- is installed as a script node in the generated XML rather than unzipped into
-- the package directory, because the client has to have run it before there is
-- a package to require. It is counted and hashed with the rest all the same —
-- it is part of the world, it is just not a file in it.
--
-- The order is the order the build sorted the files in before it counted them,
-- which it does so that the fingerprint it takes at the same time cannot depend
-- on what order the filesystem hands them back in. The cellar reads down that
-- column and gets rooms/ together at the bottom of it.
--
-- What is written here is what an unbuilt copy claims: nothing, which is the
-- truth about a world nobody has built.
local FILES = {}

local files, total = {}, 0
for _, entry in ipairs(FILES) do
    files[#files + 1] = {
        path = entry[1], lines = entry[2],
        comment = entry[3], code = entry[4], shipped = entry[5],
    }
    total = total + entry[2]
end

return { files = files, lines = total }
