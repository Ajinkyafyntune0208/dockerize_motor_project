import React from "react";
import { StyledCol, StyledRow, RowTag, LabelTag } from "./styles";
import { Col, Spinner } from "react-bootstrap";
import { useSelector } from "react-redux";
//prettier-ignore
import { _autoCorrection, SingleKey, onChangeSingle,
         _refocusOnReplace, _maxLength, isMidsegPresent
        } from "./helper";
import { Button, ErrorMsg, TextInput } from "components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";
import { useMediaPredicate } from "react-media-hook";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const RegInput = (props) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan330 = useMediaPredicate("(max-width: 330px)");
  const { temp_data } = useSelector((state) => state.home);

  //prettier-ignore
  const { register, setValue, watch, errors, onSubmitFastLane,
          onSubmit, buffer, setbtnDisable, btnDisable, 
          stepper1, setBuffer, 
         } = props;
  /*========= single regNp ip =========*/
  //watch reg no input
  const regIp = watch("regNo") || "";
  const regSplit = regIp && regIp.split("-");

  //varibles for reg inputs
  let regNo1 = regIp
    ? regSplit.length >= 2
      ? `${regSplit[0]}-${regSplit[1]}`
      : ""
    : "";
  regNo1 = regNo1 ? regNo1.replace(/\s/g, "") : ""; //trim white-spaces
  let regNo2 = regIp ? (regSplit.length === 4 ? regSplit[2] : "") : "";
  regNo2 = regNo2 ? regNo2.replace(/\s/g, "") : ""; //trim white-spaces
  let regNo3 = regIp
    ? regSplit.length === 4
      ? regSplit[3]
      : regSplit.length === 3
      ? regSplit[2]
      : ""
    : "";
  //BH check
  let isBH = regIp && regIp[0] * 1;

  //finding number of blocks
  const segmentIndexes = regIp && regSplit;

  //finding the length of the rto block
  const segmentInfo =
    regIp &&
    segmentIndexes &&
    !_.isEmpty(segmentIndexes) &&
    segmentIndexes?.length >= 1
      ? segmentIndexes[1]?.length
      : "";

  //finding the length of middle segment
  let midBlockCheck =
    regIp &&
    regIp.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
    regIp.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1;

  //checking if middile segment is present in registration number
  const MidsegmentInfo = isMidsegPresent(regIp, midBlockCheck, segmentIndexes);

  //setting maxlength of regFeild & indicating if the middle block is empty.
  let maxlen = _maxLength(isBH, regIp, segmentInfo, MidsegmentInfo);

  //Eval if reg no is complete
  const isRegComplete =
    (regNo1 && regNo3) || (regIp && regIp[0] * 1 && regIp.length > 10);

  //Signle key params
  const inputParams = {
    setValue,
    stepper1,
    setBuffer,
    onSubmitFastLane,
  };
  const regInputStateParams = { temp_data, MidsegmentInfo, segmentInfo, isBH };
  return (
    <>
      <StyledRow
        className={`w-100 d-flex no-wrap ${
          lessthan767 ? (!lessthan330 ? "mt-3" : "mt-1") : "mt-5"
        } mx-auto justify-content-center`}
      >
        <StyledCol
          sm="12"
          md="12"
          lg="9"
          xl="9"
          className="p-0 my-2 mx-auto d-flex flex-column no-wrap RegNoPage"
        >
          <RowTag className="d-flex w-100 mx-auto justify-content-center flex-nowrap">
            <Col sm="12" md="12" lg="12" xl="12" className=" p-0 m-0">
              <TextInput
                lg
                noPadding
                type="text"
                name="regNo"
                placeholder="Enter Registration No. (MH-04-AR-7070)"
                placeholderColor={"#FFFFF"}
                ref={register}
                maxLength={maxlen}
                disabled={buffer}
                onPaste={(e) => _autoCorrection(e, setValue, watch)}
                onKeyUp={(e) => SingleKey(e, inputParams, regInputStateParams)}
                onKeyDown={(e) =>
                  SingleKey(e, inputParams, regInputStateParams)
                }
                onChange={(e) =>
                  onChangeSingle(e, segmentInfo, MidsegmentInfo, isBH)
                }
                onInput={(e) => {
                  //keeping i/p blur when -- is replaced & validations are met then refocusing.
                  _refocusOnReplace(e);
                }}
              />
            </Col>
          </RowTag>
          {(errors.regNo2?.message && (!regNo2 || regNo2?.length >= 2)) ||
            (errors.regNo3?.message && (!regNo3 || regNo3?.length >= 3) && (
              <ErrorMsg>
                {(errors?.regNo1?.message &&
                (!regNo1 ||
                  regNo1?.length >= 4 ||
                  (regNo1?.length >= 1 &&
                    regNo1?.length <= 2 &&
                    !!regNo1?.match(/[^A-Za-z\s]/gi)))
                  ? errors?.regNo1?.message
                  : "") ||
                  (errors.regNo2?.message && (!regNo2 || regNo2?.length >= 2)
                    ? errors.regNo2?.message
                    : "") ||
                  (errors.regNo3?.message && (!regNo3 || regNo3?.length >= 3)
                    ? errors.regNo3?.message
                    : "")}
              </ErrorMsg>
            ))}
        </StyledCol>
        <StyledCol
          sm="12"
          md="12"
          lg="2"
          xl="2"
          className="p-0 my-2 mx-auto d-flex justify-content-center w-100"
        >
          <Button
            id={"proceedBtn"}
            buttonStyle="outline-solid"
            style={{
              ...(!(isRegComplete || !btnDisable) && {
                cursor: "not-allowed",
              }),
              ...(lessthan767 && { width: "100%" }),
            }}
            hex1={
              isRegComplete
                ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                : "#e7e7e7"
            }
            hex2={
              isRegComplete
                ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                : "#e7e7e7"
            }
            borderRadius={lessthan767 ? "30px" : "5px"}
            disabled={(isRegComplete ? false : true) || btnDisable || buffer}
            onClick={() => {
              onSubmit(1);
              setbtnDisable(true);
            }}
            height="60px"
            type="submit"
          >
            {!buffer && (
              <LabelTag regNo1={regNo1} regNo3={regNo3} regIp={regIp}>
                {"Proceed"}
              </LabelTag>
            )}
            {buffer && (
              <>
                {["", "mx-1", ""].map((i) => {
                  return (
                    <Spinner
                      variant="light"
                      as="span"
                      animation="grow"
                      size="sm"
                      className={i}
                    />
                  );
                })}
              </>
            )}
          </Button>
        </StyledCol>
      </StyledRow>
    </>
  );
};

export default RegInput;
