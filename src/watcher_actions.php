<?php

return [
    IN_ACCESS => ['IN_ACCESS', 'File was accessed (read)'],
    IN_MODIFY => ['IN_MODIFY', 'File was modified'],
    IN_ATTRIB => ['IN_ATTRIB', 'Metadata changed (e.g. permissions, mtime, etc.)'],
    IN_CLOSE_WRITE => ['IN_CLOSE_WRITE', 'File opened for writing was closed'],
    IN_CLOSE_NOWRITE => ['IN_CLOSE_NOWRITE', 'File not opened for writing was closed'],
    IN_OPEN => ['IN_OPEN', 'File was opened'],
    IN_MOVED_TO => ['IN_MOVED_TO', 'File moved into watched directory'],
    IN_MOVED_FROM => ['IN_MOVED_FROM', 'File moved out of watched directory'],
    IN_CREATE => ['IN_CREATE', 'File or directory created in watched directory'],
    IN_DELETE => ['IN_DELETE', 'File or directory deleted in watched directory'],
    IN_DELETE_SELF => ['IN_DELETE_SELF', 'Watched file or directory was deleted'],
    IN_MOVE_SELF => ['IN_MOVE_SELF', 'Watch file or directory was moved'],
    (IN_CLOSE_WRITE | IN_CLOSE_NOWRITE) => ['IN_CLOSE', 'Equals to IN_CLOSE_WRITE | IN_CLOSE_NOWRITE'],
    (IN_MOVED_FROM | IN_MOVED_TO) => ['IN_MOVE', 'Equals to IN_MOVED_FROM | IN_MOVED_TO'],
    IN_ALL_EVENTS => ['IN_ALL_EVENTS', 'Bitmask of all the above constants'],
    IN_UNMOUNT => ['IN_UNMOUNT', 'File system containing watched object was unmounted'],
    IN_Q_OVERFLOW => ['IN_Q_OVERFLOW', 'Event queue overflowed (wd is -1 for this event)'],
    IN_IGNORED => [
        'IN_IGNORED',
        'Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted',
    ],
    IN_ISDIR => ['IN_ISDIR', 'Subject of this event is a directory'],
    (IN_ONLYDIR | IN_ACCESS) => ['IN_ONLYDIR', 'Only watch pathname if it is a directory (Since Linux 2.6.15)'],
    (IN_DONT_FOLLOW | IN_CREATE) => [
        'IN_DONT_FOLLOW', 'Do not dereference pathname if it is a symlink (Since Linux 2.6.15)',
    ],
    IN_MASK_ADD => [
        'IN_MASK_ADD', 'Add events to watch mask for this pathname if it already exists (instead of replacing mask).',
    ],
    IN_ONESHOT => ['IN_ONESHOT', 'Monitor pathname for one event, then remove from watch list.'],
];
