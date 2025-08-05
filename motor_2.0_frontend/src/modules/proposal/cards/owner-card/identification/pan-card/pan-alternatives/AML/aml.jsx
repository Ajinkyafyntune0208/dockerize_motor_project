import { Col } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { amlBrokers } from "modules/proposal/proposal-constants";
import FilePicker from "components/filePicker/filePicker";
import { ErrorMsg } from "components";

export const AML = ({
  temp_data,
  panAvailability,
  pan_file,
  setpan_file,
  watch,
  register,
  fileUploadError,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  return (
    <>
      {/*Shriram AML Logic*/}
      {/* {amlBrokers(companyAlias).includes(import.meta.env.VITE_BROKER) && */}
      {panAvailability === "YES" &&
      (companyAlias === "shriram" || companyAlias === "royal_sundaram") ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4}>
          <div className="py-2">
            <FormGroupTag mandatory>Upload File</FormGroupTag>
            <FilePicker
              file={pan_file}
              setFile={setpan_file}
              watch={watch}
              register={register}
              name={"pan_file"}
              id={"pan_file"}
              placeholder={"Upload PAN Card"}
            />
            {!pan_file && fileUploadError && (
              <ErrorMsg fontSize={"12px"}>Please Upload PAN</ErrorMsg>
            )}
          </div>
        </Col>
      ) : (
        <noscript />
      )}
    </>
  );
};
