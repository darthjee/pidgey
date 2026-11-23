# Expected pack file list

Commit the explicit list of files the tarball must contain (`packaging.md`, Supply chain, Pack check). Generate it with:

```sh
cd clients/nodejs && npm pack --dry-run --json | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{for(const f of JSON.parse(s)[0].files)console.log(f.path)})' | LC_ALL=C sort > pack-files.txt
```

Then check it by hand. It should hold `README.md`, `package.json` and the three `lib/*.js` files, and nothing else: no specs, config, lockfile or coverage. If anything else shows up, narrow `files` rather than accepting it in the list.

## Files to Change
- `clients/nodejs/pack-files.txt`: new.
