# Developer documentation

## Creating a release

1. Update the version in `info.xml`, in this example version `1.2.3`.
2. Make sure `CHANGELOG.md` is up-to-date, contains the new version with correct date.
3. Commit all changes.
4. `gh auth login`
5. `gh release create v1.2.3`
6. `make appstore`
7. `gh release upload v1.2.3 build/artifacts/appstore/user_vo.tar.gz`
8. `openssl dgst -sha512 -sign ~/.nextcloud/certificates/user_vo.key build/artifacts/appstore/user_vo.tar.gz | openssl base64`
9. Submit release on https://apps.nextcloud.com/developer/apps/releases/new, using the signature from the previous command and the download link from the uploaded release asset https://github.com/NikolausDemmel/user_vo/releases/download/v1.2.3/user_vo.tar.gz