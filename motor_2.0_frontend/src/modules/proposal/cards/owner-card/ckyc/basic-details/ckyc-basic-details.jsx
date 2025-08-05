import { Relation } from "../relation/relation";
import { Organisation } from "../organisation/organisation";
import { IncorporationDate } from "../incorporation-date/incorporation-date";
import { IndustryType } from "../industry-type/Industry-type";

export const BasicCKYC = ({
  temp_data,
  fields,
  resubmit,
  verifiedData,
  fieldsNonEditable,
  errors,
  register,
  ckycValue,
  uploadFile,
  watch,
  allFieldsReadOnly,
  Controller,
  control,
  owner,
  CardData,
  poi,
  cinAvailability,
  setValue,
}) => {
  return (
    <>
      <Relation
        temp_data={temp_data}
        fields={fields}
        resubmit={resubmit}
        verifiedData={verifiedData}
        fieldsNonEditable={fieldsNonEditable}
        errors={errors}
        register={register}
        ckycValue={ckycValue}
        uploadFile={uploadFile}
        watch={watch}
        allFieldsReadOnly={allFieldsReadOnly}
      />
      <Organisation
        temp_data={temp_data}
        fields={fields}
        register={register}
        errors={errors}
        watch={watch}
        ckycValue={ckycValue}
        uploadFile={uploadFile}
        poi={poi}
        cinAvailability={cinAvailability}
      />
      <IndustryType
        fields={fields}
        temp_data={temp_data}
        register={register}
        errors={errors}
        watch={watch}
        setValue={setValue}
        control={control}
      />
      <IncorporationDate
        Controller={Controller}
        control={control}
        allFieldsReadOnly={allFieldsReadOnly}
        resubmit={resubmit}
        verifiedData={verifiedData}
        fieldsNonEditable={fieldsNonEditable}
        register={register}
        owner={owner}
        CardData={CardData}
        errors={errors}
        watch={watch}
        fields={fields}
        temp_data={temp_data}
      />
    </>
  );
};
