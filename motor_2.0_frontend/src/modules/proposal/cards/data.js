export const Identities = (company, uploadFile, poi, poa) => {
  return [
    {
      name: "PAN Number",
      id: "panNumber",
      placeholder: "Upload PAN Card Image",
      length: 10,
      fileKey: "panCard",
    },
    {
      name: "GST Number",
      id: "gstNumber",
      placeholder: "Upload GST Number Certificate",
      length: 15,
      fileKey: "gst_certificate",
    },
    {
      name: "Driving License Number",
      id: "drivingLicense",
      placeholder: "Upload Driving License Image",
      length: 16,
      fileKey: "driving_license",
    },
    {
      name: "Voter ID Number",
      id: "voterId",
      placeholder: "Upload Voter ID Card Image",
      length: 10,
      fileKey: "voter_id",
    },
    {
      name: "Passport Number",
      id: "passportNumber",
      placeholder: "Upload Passport Image",
      length: 8,
      fileKey: "passport_image",
    },
    {
      name: "e-Insurance Account Number",
      id: "eiaNumber",
      fileKey: "eiaNumber",
    },
    {
      name: "Adhaar Number",
      id: "aadharNumber",
      placeholder: "Upload Adhaar Card Image",
      length: 12,
      fileKey: "aadharCard",
    },
    {
      name: "NREGA Job Card",
      id: "nregaJobCard",
      placeholder: "Upload NREGA Card Image",
      length: 18,
      fileKey: "nrega_job_card_image",
    },
    {
      name: "National Population Letter",
      id: "nationalPopulationRegisterLetter",
      placeholder: "Upload Letter",
      length: 20,
      fileKey: "national_population_register_letter_image",
    },
    {
      name: "Registration Certificate",
      id: "registrationCertificate",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "registration_certificate_image",
    },
    {
      name: "Udyog Certificate",
      id: "udyog",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "udyog",
    },
    {
      name: "Udyam Certificate",
      id: "udyam",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "udyam",
    },
    {
      name: "Passport File Number",
      id: "passportFileNumber",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "passportFileNumber",
    },
  ];
};

export const identitiesCompany = (company, uploadFile, poi, poa) => {
  return [
    {
      name: "PAN Number",
      id: "panNumber",
      placeholder: "Upload PAN Card Image",
      length: 10,
      fileKey: "panCard",
    },
    {
      name: "GST Number",
      id: "gstNumber",
      placeholder: "Upload GST Number Certificate",
      length: 15,
      fileKey: "gst_certificate",
    },
    {
      name: "Driving License Number",
      id: "drivingLicense",
      placeholder: "Upload Driving License Image",
      length: 16,
      fileKey: "driving_license",
    },
    {
      name: "Voter ID Number",
      id: "voterId",
      placeholder: "Upload Voter ID Card Image",
      length: 10,
      fileKey: "voter_id",
    },
    {
      name: "Passport Number",
      id: "passportNumber",
      placeholder: "Upload Passport Image",
      length: 8,
      fileKey: "passport_image",
    },
    {
      name: "e-Insurance Account Number",
      id: "eiaNumber",
      fileKey: "eiaNumber",
    },
    {
      name: "Adhaar Number",
      id: "aadharNumber",
      placeholder: "Upload Adhaar Card Image",
      length: 12,
      fileKey: "aadharCard",
    },
    {
      name: "CIN Number",
      id: "cinNumber",
      placeholder: "Upload CIN Number Certificate",
      fileKey: "cinNumber",
    },
    {
      name: "Registration Certificate",
      id: "registrationCertificate",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "registration_certificate_image",
    },
    {
      name: "Certificate of Incorporation",
      id: "cretificateOfIncorporaion",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "certificate_of_incorporation_image",
    },
    {
      name: "Udyog Certificate",
      id: "udyog",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "udyog",
    },
    {
      name: "Udyam Certificate",
      id: "udyam",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "udyam",
    },
    {
      name: "Passport File Number",
      id: "passportFileNumber",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "passportFileNumber",
    },
  ];
};

export const MethodError = [
  "Try With CIN Number in Case of Company",
  "Please upload photograph to complete proposal.",
  "Ckyc verification failed, please try using a different ID.",
  "CKYC verification failed, please try using a different ID.",
  "CKYC verification failed. Try other method",
  "CKYC Verification failed using PAN number",
  "Proposal integrity check failed. You will be redirected to quote page.",
  "Changes in address will require Re-Verification.",
  "CKYC completed. Please try with CKYC/CIN number",
  "CKYC verification failed, please try using a other ID.",
  "CKYC Invalid, Please Enter POI Type",
  "CKYC verification failed. Please check the following details.",
  "CKYC verification failed, please try using another ID.",
];

export const IdError = [
  "CKYC verification failed using CKYC number. Please check the entered CKYC Number or try with another method",
];
