import { Row, Col, Form } from "react-bootstrap";
import { ErrorMsg, Button } from "components";
import { _haptics } from "utils";
//personal owner details
import { PersonalDetails } from "./personal-details/personal-details";
import { AddressDetails } from "./address-details/address-details";
import { BasicCKYC } from "./ckyc/basic-details/ckyc-basic-details";
import { Gender } from "./personal-details/gender/gender";
import { MaritalStatus } from "./personal-details/marital-status/marital-status";
import { Occupation } from "./personal-details/occupation/occupation";
import { PanCard } from "./identification/pan-card/pan-card";
import { GSTIN } from "./identification/gstin/gstin";
import { CinAvailability } from "./ckyc/cin-availability/cin-availability";
import { BankDetails } from "./bank-details/bank-details";
//AML
import { PanAvailability } from "./ckyc/pan-availability/pan-availability";
//CKYC
import { CkycAvailability } from "./ckyc/ckyc-availability/ckyc-availability";
import { IncorporationDate } from "./personal-details/date-of-incorporation/date-of-incorporation";
import { CkycType } from "./ckyc/ckyc-type/ckyc-type";
import { ProfilePhoto } from "./ckyc/profile-photo/profile-photo";
import { ProofOfIdentity } from "./ckyc/proof-of-identity/proof-of-identity";
import { ProofOfAddress } from "./ckyc/proof-of-address/proof-of-address";
import { SelectedCkycType } from "./ckyc/selected-ckyc-type/selected-ckyc-type";
import { CkycNumber } from "./ckyc/ckyc-number/ckyc-number";
export const OwnerForm = ({
  //prettier-ignore
  temp_data, owner,CardData, handleSubmit,
  onSubmitOwner, register, errors, resubmit,
  watch, fields, allFieldsReadOnly, verifiedData,
  fieldsNonEditable, Controller, control, setValue,
  enquiry_id, ckycValue, uploadFile, radioValue,
  setRadioValue, gender, panAvailability, 
  setPanAvailability, setpan_file, setckycValue,
  isCkycDetailsRejected, cinAvailability,
  setCinAvailability, renewalUploadReadOnly,
  selectedIdentity, identity, ckycFields,
  poi_file, setpoi_file,poi_back_file, setpoi_back_file, fileUploadError,
  fileValidationText, poi_identity, ckycTypes,
  poi_disabled, selectedpoiIdentity,
  poa_file, setpoa_file,poa_back_file,setpoa_back_file, poa_identity,
  selectedpoaIdentity, poa_disabled, photo,
  setPhoto, lessthan768, acceptedExt,
  form60, form49, setForm60, setForm49,
  token, type, fieldEditable, conditionChk,
  Theme, pan_file, errorStep, occupation,
  poi, poa, loading, lessthan376, setuploadFile
}) => {
  return (
    <>
      <Form onSubmit={handleSubmit(onSubmitOwner)} autoComplete="none">
        <Row
          style={{
            margin: lessthan768
              ? "-60px -30px 20px -30px"
              : "-60px -20px 20px -30px",
          }}
          className="p-2"
        >
          {/*Name, Mobile No, Email, DOB*/}
          <PersonalDetails
            temp_data={temp_data}
            register={register}
            errors={errors}
            resubmit={resubmit}
            watch={watch}
            fields={fields}
            allFieldsReadOnly={allFieldsReadOnly}
            verifiedData={verifiedData}
            ErrorMsg={ErrorMsg}
            fieldsNonEditable={fieldsNonEditable}
            Controller={Controller}
            control={control}
            owner={owner}
            CardData={CardData}
            setValue={setValue}
            enquiry_id={enquiry_id}
          />
          <BasicCKYC
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
            Controller={Controller}
            control={control}
            owner={owner}
            CardData={CardData}
            poi={poi}
            cinAvailability={cinAvailability}
            setValue={setValue}
          />
          <Gender
            temp_data={temp_data}
            CardData={CardData}
            owner={owner}
            fields={fields}
            allFieldsReadOnly={allFieldsReadOnly}
            fieldsNonEditable={fieldsNonEditable}
            register={register}
            errors={errors}
            watch={watch}
            setValue={setValue}
            radioValue={radioValue}
            setRadioValue={setRadioValue}
            gender={gender}
            enquiry_id={enquiry_id}
            resubmit={resubmit}
            verifiedData={verifiedData}
          />
          <PanAvailability
            temp_data={temp_data}
            panAvailability={panAvailability}
            setPanAvailability={setPanAvailability}
            setpan_file={setpan_file}
            register={register}
            setValue={setValue}
            owner={owner}
            CardData={CardData}
            watch={watch}
            setuploadFile={setuploadFile}
            ckycValue={ckycValue}
          />
          <CkycAvailability
            temp_data={temp_data}
            CardData={CardData}
            fields={fields}
            ckycValue={ckycValue}
            setckycValue={setckycValue}
            setValue={setValue}
            register={register}
            errors={errors}
            isCkycDetailsRejected={isCkycDetailsRejected}
            fieldsNonEditable={fieldsNonEditable}
          />
          <CinAvailability
            temp_data={temp_data}
            fields={fields}
            register={register}
            setValue={setValue}
            cinAvailability={cinAvailability}
            setCinAvailability={setCinAvailability}
            resubmit={resubmit}
            uploadFile={uploadFile}
            ckycValue={ckycValue}
            owner={owner}
            CardData={CardData}
          />
          <CkycType
            temp_data={temp_data}
            errors={errors}
            fields={fields}
            ckycValue={ckycValue}
            uploadFile={uploadFile}
            errorStep={errorStep}
            panAvailability={panAvailability}
            register={register}
            resubmit={resubmit}
            renewalUploadReadOnly={renewalUploadReadOnly}
            selectedIdentity={selectedIdentity}
            identity={identity}
            ckycFields={ckycFields}
            watch={watch}
            fieldsNonEditable={fieldsNonEditable}
          />
          <ProofOfIdentity
            temp_data={temp_data}
            poi={poi}
            uploadFile={uploadFile}
            poi_file={poi_file}
            setpoi_file={setpoi_file}
            poi_back_file={poi_back_file}
            setpoi_back_file={setpoi_back_file}
            fields={fields}
            ckycValue={ckycValue}
            cinAvailability={cinAvailability}
            allFieldsReadOnly={allFieldsReadOnly}
            register={register}
            panAvailability={panAvailability}
            errors={errors}
            watch={watch}
            resubmit={resubmit}
            fileUploadError={fileUploadError}
            fileValidationText={fileValidationText}
            poi_identity={poi_identity}
            ckycFields={ckycFields}
            ckycTypes={ckycTypes}
            poi_disabled={poi_disabled}
            selectedpoiIdentity={selectedpoiIdentity}
          />
          <ProofOfAddress
            temp_data={temp_data}
            poa_file={poa_file}
            setpoa_file={setpoa_file}
            poa_back_file={poa_back_file}
            setpoa_back_file={setpoa_back_file}
            fields={fields}
            uploadFile={uploadFile}
            ckycValue={ckycValue}
            ckycFields={ckycFields}
            register={register}
            errors={errors}
            fileUploadError={fileUploadError}
            watch={watch}
            poa={poa}
            ckycTypes={ckycTypes}
            poa_identity={poa_identity}
            selectedpoaIdentity={selectedpoaIdentity}
            poa_disabled={poa_disabled}
            fileValidationText={fileValidationText}
          />
          <CkycNumber
            fields={fields}
            ckycValue={ckycValue}
            register={register}
            resubmit={resubmit}
            watch={watch}
            fieldsNonEditable={fieldsNonEditable}
            errors={errors}
          />
          <ProfilePhoto
            temp_data={temp_data}
            fields={fields}
            ckycValue={ckycValue}
            uploadFile={uploadFile}
            photo={photo}
            setPhoto={setPhoto}
            lessthan768={lessthan768}
            acceptedExt={acceptedExt}
            fileUploadError={fileUploadError}
            fileValidationText={fileValidationText}
            watch={watch}
            register={register}
          />
          <SelectedCkycType
            temp_data={temp_data}
            fields={fields}
            uploadFile={uploadFile}
            resubmit={resubmit}
            watch={watch}
            identity={identity}
            fieldsNonEditable={fieldsNonEditable}
            ckycValue={ckycValue}
            selectedIdentity={selectedIdentity}
            register={register}
            errors={errors}
            ckycTypes={ckycTypes}
          />
          <IncorporationDate
            identity={identity}
            ckycValue={ckycValue}
            Controller={Controller}
            control={control}
            register={register}
            errors={errors}
            fieldsNonEditable={fieldsNonEditable}
            temp_data={temp_data}
          />
          <PanCard
            temp_data={temp_data}
            owner={owner}
            fields={fields}
            identity={identity}
            poa_identity={poa_identity}
            poi_identity={poi_identity}
            panAvailability={panAvailability}
            ckycValue={ckycValue}
            register={register}
            watch={watch}
            errors={errors}
            resubmit={resubmit}
            fieldsNonEditable={fieldsNonEditable}
            renewalUploadReadOnly={renewalUploadReadOnly}
            allFieldsReadOnly={allFieldsReadOnly}
            pan_file={pan_file}
            setpan_file={setpan_file}
            fileUploadError={fileUploadError}
            form60={form60}
            form49={form49}
            setForm60={setForm60}
            setForm49={setForm49}
          />
          <GSTIN
            temp_data={temp_data}
            fields={fields}
            poa_identity={poa_identity}
            poi_identity={poi_identity}
            identity={identity}
            token={token}
            register={register}
            errors={errors}
            type={type}
            resubmit={resubmit}
            watch={watch}
          />
          <Occupation
            temp_data={temp_data}
            owner={owner}
            CardData={CardData}
            occupation={occupation}
            watch={watch}
            register={register}
            errors={errors}
            enquiry_id={enquiry_id}
            fields={fields}
            allFieldsReadOnly={allFieldsReadOnly}
          />
          <MaritalStatus
            temp_data={temp_data}
            owner={owner}
            CardData={CardData}
            fields={fields}
            watch={watch}
            register={register}
            errors={errors}
            allFieldsReadOnly={allFieldsReadOnly}
          />
          <AddressDetails
            temp_data={temp_data}
            resubmit={resubmit}
            verifiedData={verifiedData}
            fieldEditable={fieldEditable}
            fieldsNonEditable={fieldsNonEditable}
            register={register}
            errors={errors}
            watch={watch}
            CardData={CardData}
            owner={owner}
            enquiry_id={enquiry_id}
            setValue={setValue}
          />

          {["sbi", "universal_sompo", "hdfc_ergo"].includes(
            temp_data?.selectedQuote?.companyAlias
          ) &&
            fields.includes("cisEnabled") && (
              <BankDetails
                temp_data={temp_data}
                register={register}
                errors={errors}
                watch={watch}
                CardData={CardData}
                fields={fields}
                owner={owner}
                enquiry_id={enquiry_id}
                setValue={setValue}
                allFieldsReadOnly={allFieldsReadOnly}
              />
            )}
          {/*---This hidden input is for popup notification---*/}
          <input type="hidden" ref={register} name="popupPreview" value={"Y"} />
          {/*-x-This hidden input is for popup notification-x-*/}
          <Col
            sm={12}
            lg={12}
            md={12}
            xl={12}
            className="d-flex justify-content-center mt-5 mx-auto"
          >
            <Button
              type="submit"
              buttonStyle="outline-solid"
              className=""
              shadow={"none"}
              id="owner-submit"
              disabled={fields.includes("ckyc") && loading}
              hex1={
                Theme?.proposalProceedBtn?.hex1
                  ? Theme?.proposalProceedBtn?.hex1
                  : "#4ca729"
              }
              hex2={
                Theme?.proposalProceedBtn?.hex2
                  ? Theme?.proposalProceedBtn?.hex2
                  : "#4ca729"
              }
              borderRadius="5px"
              color="white"
              onClick={() => _haptics([100, 0, 50])}
            >
              <text
                style={{
                  fontSize: "15px",
                  padding: "-20px",
                  margin: "-20px -5px -20px -5px",
                  fontWeight: "400",
                }}
              >
                {fields.includes("ckyc") && loading
                  ? "Please Wait..."
                  : Number(temp_data?.ownerTypeId) === 1 && conditionChk
                  ? `Proceed to Nominee${!lessthan376 ? " Details" : ""}`
                  : `Proceed to Vehicle${!lessthan376 ? " Details" : ""}`}
              </text>
            </Button>
          </Col>
        </Row>
      </Form>
    </>
  );
};
