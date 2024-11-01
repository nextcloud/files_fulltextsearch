<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog


### 27.0.0

- compat nc27


### 26.0.0

- compat nc26


### 25.0.0

- compat nc25


### 24.0.0

- compat nc24


### 23.0.0

- Fixing events


### 21.0.2 

- Adding some logging
- Fixing some crashing


### 21.0.1

- Fixing some issue with null File
- Fixing issue with occ fulltextsearch:command
- adding few logging


### 21.0.0

- Compat NC21


### 20.0.0

- upgrade deps
- better QueryBuilder
- new listeners
- new events
- small bugfixes


### 2.0.0

- compat nc20


### 1.4.3

- compat nc19
- small bugfixes


### 1.4.2

- ignore .part files


### 1.4.1

- fixing a conflict with onlyoffice


### 1.4.0 (nc18)

- compat nc18


### 1.3.6

- bugfix: index chunk path. 


### 1.3.5

- allow link_attributes


### 1.3.4

- new event onGetConfig
- new event onIndexComparing


### 1.3.3

- upgrading external storage


### 1.3.2

- upgrade on composer lib.


### 1.3.1

- [bugfix] issue with empty userid on guest upload and files:scan


### 1.3.0 (NC16)

- Implementing indexing-by-chunks


### 1.2.4

- indexing external files should now work as expected
- fixing an issue when creating a new user
- fixing issue on meta not being updated when unsharing/sharing files
- content of a folder is also updated when meta is edited.

 
### 1.2.3

- init DocumentAccess on FilesDocuments creation.
- register Services on hook
- prepare createIndex for NC 15.0.1


### 1.2.2

- fixing small logging issue
- fixing issue with groupfolders


### 1.2.1

- fix an issue with addLink()
- fix an issue when generating documents


### 1.2.0 (NC15)

- Compat NC15 + full php7.
- during file events, path can be null.


### 1.0.2

- improvement: display more info while indexing files.
- bug: fix an issue while getting shares on a file.
- bug: should not OCR big files anymore.
- bug: index on huge file system should not timeout (Force Quit).
- misc: removing compat with NC12.


### 1.0.1

bug: some issue with strange folders.


### 1.0.0

First stable release


### 0.99.2 

- fix identification of source of files.


### 0.99.1 Release Candidate 2

- bugfix: option within_dir
- improvement: tags/metatags/subtags
- improvement: document and searchrequest are dispatched via event
- improvement: info update during :index
- improvement: no more chunks
- tesseract is removed from files_fts 


### 0.99.0 Release Candidate


### 0.7.0

- bugfixes
- changes in settings does not require a full reindex anymore
- compat with strange userId in shared name
- limit search within current directory
- filters by extension
- improved index and search within local files, external filesystem and group folders
- compat fulltextsearch 0.7.0 and fulltextsearch navigation app.



### 0.6.0

- Options panel
- bugfixes



### 0.5.0

- more options
- fixing some office mimetype detection



### v0.4.0

- fullnextsearch -> fulltextsearch
- settings panel


### v0.3.1

- bugfixes.



### BETA v0.3.0

- First Beta

