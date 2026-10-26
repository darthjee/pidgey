import parseBody from '../../../../assets/js/client/parseBody.js';

function response(status, text) {
  return { status, text: () => Promise.resolve(text) };
}

describe('parseBody', () => {
  it('returns {} for a 204 response without reading the body', async () => {
    const noContent = { status: 204, text: jasmine.createSpy('text') };

    expect(await parseBody(noContent)).toEqual({});
    expect(noContent.text).not.toHaveBeenCalled();
  });

  it('returns {} for an empty body', async () => {
    expect(await parseBody(response(200, ''))).toEqual({});
  });

  it('parses a JSON body', async () => {
    expect(await parseBody(response(200, '{"id":1}'))).toEqual({ id: 1 });
  });

  it('returns {} for a body that is not JSON', async () => {
    expect(await parseBody(response(502, '<html>Bad Gateway</html>'))).toEqual({});
  });
});
