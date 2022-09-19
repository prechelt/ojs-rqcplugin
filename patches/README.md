# reviewqualitycollector/patches/README.md

created: Lutz Prechelt, 2022-09-16

These are modifications that the RQC plugin needs in either the OJS or PKPlib codebases.

I will usually have submitted them as pull requests, but until those are accepted,
I will simply patch my fork each time I update it.

## How to create a patch for OJS

- Modify the file(s) in the OJS codebase.
  Test your changes.
- `ptch=nameofpatch; ptchfile=${ptch}-$(date --iso).patch; patchedfiles="relevantfiles morefiles"`
- `pd=$rd/patches`
- `git diff -- $patchedfiles >$pd/3.3/$ptchfile; cp $pd/3.3/$ptchfile $pd/3.4/$ptchfile`
- Now in the bare "ojs" workdir:
  `init_ojs33; git pull upstream main; git pull upstream stable-3_3_0`
- `git switch stable-3_3_0; git checkout -b ${ptch}_33`
- `git apply $pd/3.3/$ptchfile`
- `git a $patchedfiles`
- `git c -m"<explanation of patch>"`
- `git push`
- `git switch main`
- At GitHub, make pull request
- `git checkout -b ${ptch}_34`
- `git apply $pd/3.4/$ptchfile`
  If patch does not apply, correct or re-create it, store it in `$pd/3.4/$ptchfile`,
  attempt `git apply` again.
- `git a $patchedfiles`
- `git c -m"<explanation of patch>"`
- `git push`

## How to create a patch for PKPlib

much like above, with the following changes:
- `pd=$rd/patches/lib/pkp`
- before the `diff`, do `cd lib/pkp`
- change into the bare "pkp-lib" workdir, not the bare "ojs" workdir.

## How to apply a patch

Note that some patches exists in separate versions for different OJS or PKPlib versions.
Make sure you only apply the pertinent ones.

### For OJS patches

`git apply patches/name_of_patch.patch`

### For PKPlib patches

`(cd lib/pkp; git apply patches/name_of_patch.patch)`
