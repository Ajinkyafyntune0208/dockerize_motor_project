import * as yup from 'yup';

export const validation = {
  name: yup.string()
    .required('Required')
    .matches(/^([A-Za-z\s])+$/, 'Must contain only alphabets'),
  email: yup.string()
    .email('Please enter valid email id')
    .required('Email id is required'),
  mobile: yup.string()
    .required('Mobile No. is required')
    .min(10, 'Mobile No. should be 10 digits')
    .max(10, 'Mobile No. should be 10 digits')
    .matches(/^[6-9][0-9]{9}$/, 'Not valid number')
    .test('invalid', 'Not valid number', (value) => {
      return !/^[9]{10}$/.test(value);
    }),
  pincode: yup.string()
    .required('Required')
    .matches(/^[0-9]+$/, 'Must be only digits')
    .min(6, 'Must be 6 digits')
    .max(6, 'Must be 6 digits'),
  age: (minAge = 0, maxAge = 100) => yup.number()
    .typeError('Age is required')
    .min(minAge, `Age should be above ${minAge - 1} year`).max(maxAge, `Age should be below ${maxAge + 1} years`)
    .required('Age is required'),
  required: yup.string().required('Required'),
  pan_no: yup.string()
    .required('PAN no required')
    .matches(/[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/, 'PAN number invalid'),
  gst_no: yup.string()
    .required('GST no required')
    .matches(/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/, 'GST number invalid'),
}
