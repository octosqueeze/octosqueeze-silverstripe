
- (additional clean up) deleting all unpublished images?
- (additional clean up) deleted images remain in assets, task to remove such orphans too?
- (additional clean up) reset all filesystem (assets image file tables etc.) to the initial state

- save image hash in OC db, if file with the same hash has been compressed previously and still exists as a file (! with the same compression settings) - return it instead and save compression credit
