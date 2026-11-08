const response = require('../src/utils/response');

// res palsu: status() bisa dirantai ke json() seperti Express
const makeRes = () => {
  const res = {};
  res.status = jest.fn().mockReturnValue(res);
  res.json = jest.fn().mockReturnValue(res);
  return res;
};

describe('response.success', () => {
  test('default 200 dengan pesan Success', () => {
    const res = makeRes();
    response.success(res, { id: 1 });
    expect(res.status).toHaveBeenCalledWith(200);
    expect(res.json).toHaveBeenCalledWith({ success: true, message: 'Success', data: { id: 1 } });
  });

  test('kode dan pesan bisa diganti (201 Created)', () => {
    const res = makeRes();
    response.success(res, [], 'Dibuat', 201);
    expect(res.status).toHaveBeenCalledWith(201);
    expect(res.json.mock.calls[0][0].message).toBe('Dibuat');
  });

  test('data null tetap dikirim apa adanya', () => {
    const res = makeRes();
    response.success(res, null);
    expect(res.json.mock.calls[0][0].data).toBeNull();
  });
});

describe('response.error', () => {
  test('default 500 tanpa field error', () => {
    const res = makeRes();
    response.error(res);
    expect(res.status).toHaveBeenCalledWith(500);
    expect(res.json).toHaveBeenCalledWith({ success: false, message: 'Internal Server Error' });
  });

  test('menyertakan detail error bila diberikan', () => {
    const res = makeRes();
    response.error(res, 'Gagal', 502, 'ECONNREFUSED');
    expect(res.status).toHaveBeenCalledWith(502);
    expect(res.json.mock.calls[0][0]).toEqual({ success: false, message: 'Gagal', error: 'ECONNREFUSED' });
  });
});

describe('response.notFound', () => {
  test('404 dengan pesan bawaan', () => {
    const res = makeRes();
    response.notFound(res);
    expect(res.status).toHaveBeenCalledWith(404);
    expect(res.json.mock.calls[0][0].message).toBe('Resource not found');
  });

  test('pesan khusus', () => {
    const res = makeRes();
    response.notFound(res, 'Produk tidak ada');
    expect(res.json.mock.calls[0][0].message).toBe('Produk tidak ada');
  });
});

describe('response.validationError', () => {
  test('400 tanpa daftar errors', () => {
    const res = makeRes();
    response.validationError(res);
    expect(res.status).toHaveBeenCalledWith(400);
    expect(res.json.mock.calls[0][0]).toEqual({ success: false, message: 'Validation Error' });
  });

  test('400 dengan daftar errors per field', () => {
    const res = makeRes();
    response.validationError(res, 'Input salah', { nama: 'wajib diisi' });
    expect(res.json.mock.calls[0][0].errors).toEqual({ nama: 'wajib diisi' });
  });
});
