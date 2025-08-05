import { useEffect, useState } from "react";
import { Col, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import _ from "lodash";
import { useDispatch } from "react-redux";
import { getGender } from "../../../../proposal.slice";
import { ErrorMsg } from "components";

export const Gender = ({
  temp_data,
  CardData,
  owner,
  fields,
  allFieldsReadOnly,
  fieldsNonEditable,
  register,
  errors,
  watch,
  setValue,
  radioValue,
  setRadioValue,
  gender,
  enquiry_id,
  resubmit,
  verifiedData,
}) => {
  const dispatch = useDispatch();

  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const GenderIP = watch("gender");

  const [isGenderCalled, setGenderCalled] = useState(false);
  useEffect(() => {
    if (
      companyAlias &&
      Number(temp_data?.ownerTypeId) === 1 &&
      !isGenderCalled
    ) {
      dispatch(
        getGender({ companyAlias: companyAlias, enquiryId: enquiry_id })
      );
      setGenderCalled(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyAlias, temp_data?.ownerTypeId]);

  const radios = !_.isEmpty(gender)
    ? gender?.map(({ code, name }, index) => {
        return { name, val: String(index), value: code, code };
      })
    : [];

  //temporary gender fix
  const genderCodeCheck = !_.isEmpty(
    _.compact(
      gender?.map((item) =>
        item?.code === temp_data?.userProposal?.gender ? item : ""
      )
    )
  );

  //gender selection if card data has gender value but owner doesn't.
  useEffect(() => {
    if (_.isEmpty(owner) && !_.isEmpty(CardData?.owner)) {
      setRadioValue(CardData?.owner?.gender);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData.owner]);

  //default gender selection | select gender if owner and card data does not have gender but it's present in user proposal
  useEffect(() => {
    const isOwnerEmpty = _.isEmpty(owner) && _.isEmpty(CardData?.owner);
    const isGenderInfoAbsent =
      !GenderIP &&
      !temp_data?.userProposal?.gender &&
      !temp_data?.userProposal?.genderName;
    const isTempDataPresent = !_.isEmpty(temp_data);

    const genderPrefillCondition =
      isOwnerEmpty && isGenderInfoAbsent && isTempDataPresent;

    setTimeout(() => {
      if (genderPrefillCondition) {
        Number(temp_data?.ownerTypeId) &&
          !_.isEmpty(radios) &&
          !GenderIP &&
          setRadioValue(
            !_.isEmpty(gender)
              ? !_.isEmpty(
                  gender?.map(
                    ({ name }) =>
                      name.toLowerCase() === "male" ||
                      name.toLowerCase() === "m"
                  )
                )
                ? gender?.filter(
                    ({ name }) =>
                      name.toLowerCase() === "male" ||
                      name.toLowerCase() === "m"
                  )[0]?.code
                : ""
              : ""
          );
        let genderHidden = !_.isEmpty(gender)
          ? !_.isEmpty(
              gender?.map(
                ({ name }) =>
                  name.toLowerCase() === "male" || name.toLowerCase() === "m"
              )
            )
            ? gender?.filter(
                ({ name }) =>
                  name.toLowerCase() === "male" || name.toLowerCase() === "m"
              )[0]?.name
            : ""
          : "";
        genderHidden && setValue("genderName", genderHidden);
      }
      //if owner and card data are not present, then set gender
      if (
        (_.isEmpty(owner) &&
          _.isEmpty(CardData?.owner) &&
          !GenderIP &&
          temp_data?.userProposal?.genderName) ||
        (!_.isEmpty(gender) && CardData?.owner?.gender && !genderCodeCheck)
      ) {
        //Gender Index check
        !_.isEmpty(
          _.compact(
            gender?.filter(
              (item) =>
                item?.name.toLowerCase() ===
                temp_data?.userProposal?.genderName.toLowerCase()
            )
          )
        ) &&
          setRadioValue(
            _.compact(
              gender?.filter(
                (item) =>
                  item?.name.toLowerCase() ===
                  temp_data?.userProposal?.genderName.toLowerCase()
              )
            )[0]?.code
          );
      }
    }, 500);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData?.owner, radios, owner]);

  return (
    <>
      {fields.includes("gender") && Number(temp_data?.ownerTypeId) === 1 && (
        <Col
          xs={12}
          sm={12}
          md={12}
          lg={6}
          xl={4}
          className=""
        >
          <FormGroupTag
            style={{ paddingTop: "10px" }}
            mandatory
          >
            Gender
          </FormGroupTag>
          <div
            className=""
            style={{ width: "100%", paddingTop: "2px" }}
          >
            <ButtonGroupTag
              toggle
              style={{ width: "100%" }}
            >
              {radios.map((radio, idx) => (
                <ToggleButton
                  style={{
                    minWidth: "fill-available",
                    width: "100%",
                    minHeight: "32px",
                  }}
                  key={idx}
                  className={
                    radio.val === "0"
                      ? `mb-2 mr-4 index-key${idx}`
                      : `mb-2 index-key${idx}`
                  }
                  type="radio"
                  variant="secondary"
                  readOnly={
                    (allFieldsReadOnly && CardData?.owner?.genderName) ||
                    (resubmit && verifiedData?.includes("genderName")) ||
                    (CardData?.owner?.genderName && fieldsNonEditable)
                  }
                  ref={register}
                  size="sm"
                  tabIndex={"0"}
                  id={`index-key${idx}`}
                  onKeyDown={(e) => {
                    if (e.keyCode === 32 && !allFieldsReadOnly) {
                      e.preventDefault();
                      document.getElementById(`index-key${idx}`) &&
                        document.getElementById(`index-key${idx}`).click();
                    }
                  }}
                  name="gender1"
                  value={radio.code}
                  checked={radioValue === radio.code}
                  onInput={() => setValue("genderName", radio.name)}
                  onChange={(e) => {
                    (!allFieldsReadOnly || !CardData?.owner?.genderName) &&
                      !(CardData?.owner?.genderName && fieldsNonEditable) &&
                      setRadioValue(e.target.value);
                  }}
                >
                  {radio.name}
                </ToggleButton>
              ))}
            </ButtonGroupTag>
          </div>
          <input
            type="hidden"
            name="gender"
            value={radioValue}
            ref={register}
          />
          <input
            type="hidden"
            name="genderName"
            // value={genderName}
            ref={register}
          />
          {!!errors?.gender && (
            <ErrorMsg
              fontSize={"12px"}
              style={{ marginTop: "-3px" }}
            >
              {errors?.gender?.message}
            </ErrorMsg>
          )}
        </Col>
      )}
    </>
  );
};
