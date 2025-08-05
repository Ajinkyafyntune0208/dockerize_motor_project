import React from "react";
import Name from "./name/name";
import OtherDetails from "./other-details/other-details";
import PropTypes from "prop-types";
import { usePrefillLeadDetails } from "./personal-details-hooks";

export const PersonalDetails = ({
  temp_data,
  register,
  errors,
  resubmit,
  watch,
  fields,
  allFieldsReadOnly,
  verifiedData,
  fieldsNonEditable,
  Controller,
  control,
  owner,
  CardData,
  setValue,
  enquiry_id,
}) => {
  usePrefillLeadDetails(temp_data, CardData, owner, fields, setValue);
  return (
    <>
      <Name
        temp_data={temp_data}
        register={register}
        errors={errors}
        resubmit={resubmit}
        watch={watch}
        fields={fields}
        allFieldsReadOnly={allFieldsReadOnly}
        verifiedData={verifiedData}
        fieldsNonEditable={fieldsNonEditable}
        setValue={setValue}
        CardData={CardData}
      />
      <OtherDetails
        temp_data={temp_data}
        register={register}
        errors={errors}
        resubmit={resubmit}
        watch={watch}
        fields={fields}
        verifiedData={verifiedData}
        fieldsNonEditable={fieldsNonEditable}
        Controller={Controller}
        control={control}
        owner={owner}
        CardData={CardData}
        enquiry_id={enquiry_id}
      />
    </>
  );
};

PersonalDetails.propTypes = {
  temp_data: PropTypes.object,
  register: PropTypes.func,
  errors: PropTypes.object,
  resubmit: PropTypes.bool,
  watch: PropTypes.func,
  fields: PropTypes.arrayOf(PropTypes.string),
  verifiedData: PropTypes.arrayOf(PropTypes.string),
  fieldsNonEditable: PropTypes.bool,
  Controller: PropTypes.elementType,
  control: PropTypes.object,
  owner: PropTypes.object,
  CardData: PropTypes.object,
  allFieldsReadOnly: PropTypes.bool,
  setValue: PropTypes.func,
  enquiry_id: PropTypes.string,
};
