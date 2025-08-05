import { useEffect, useState } from "react";
import { Col, Form } from "react-bootstrap";
import _ from "lodash";
import { getOccupation } from "../../../../proposal.slice";
import { ErrorMsg } from "components";
import { useDispatch } from "react-redux";
import { FormGroupTag } from "modules/proposal/style";
import styled from "styled-components";

export const Occupation = ({
  temp_data,
  owner,
  CardData,
  occupation,
  watch,
  register,
  errors,
  enquiry_id,
  fields,
  allFieldsReadOnly,
}) => {
  const dispatch = useDispatch();
  const companyAlias = temp_data?.selectedQuote?.companyAlias;

  //occupation
  const [isOccupationCalled, setOccupationCalled] = useState(false);
  useEffect(() => {
    if (
      companyAlias &&
      Number(temp_data?.ownerTypeId) === 1 &&
      !isOccupationCalled
    ) {
      dispatch(
        getOccupation({ companyAlias: companyAlias, enquiryId: enquiry_id })
      );
      setOccupationCalled(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyAlias, temp_data?.ownerTypeId]);

  const Occupations = !_.isEmpty(occupation)
    ? occupation?.map(({ name, id }) => {
        return { name, id };
      })
    : [];

  //setting hidden i/p
  const OccupationType =
    watch("occupation") || owner?.occupation || CardData?.owner?.occupation;

  const OccupationName = Occupations.filter(
    ({ id }) => id === OccupationType
  )[0]?.name;

  return (
    <>
      {fields.includes("occupation") &&
        Number(temp_data?.ownerTypeId) === 1 && (
          <Col
            xs={12}
            sm={12}
            md={12}
            lg={6}
            xl={4}
            className=""
          >
            <div className="py-2 fname">
              <FormGroupTag mandatory>Occupation Type</FormGroupTag>
              <Form.Control
                as="select"
                autoComplete="none"
                size="sm"
                ref={register}
                name="occupation"
                readOnly={allFieldsReadOnly}
                className="title_list"
                style={{
                  cursor: "pointer",
                  whiteSpace: "nowrap",
                  textOverflow: "ellipsis",
                }}
                isInvalid={errors?.occupation}
              >
                (
                <option
                  selected={import.meta.env.VITE_BROKER === "ACE"}
                  value={"@"}
                >
                  Select
                </option>
                )
                {Occupations.map(({ name, id, priority }, index) => (
                  <option
                    key={id}
                    style={{ cursor: "pointer" }}
                    selected={
                      CardData?.owner?.occupation === id ||
                      (import.meta.env.VITE_BROKER === "OLA" &&
                        name === "Business / Professional Services") ||
                      (import.meta.env.VITE_BROKER === "ACE" &&
                        (priority * 1 === 1 ||
                          name === "others" ||
                          name === "Others" ||
                          name === "Other" ||
                          name === "other"))
                    }
                    value={id}
                  >
                    {name}
                  </option>
                ))}
              </Form.Control>
            </div>
            {watch("occupation") && (
              <input
                type="hidden"
                ref={register}
                name="occupationName"
                value={OccupationName}
              />
            )}
            {!!errors?.occupation && (
              <ErrorMsg
                fontSize={"12px"}
                style={{ marginTop: "-3px" }}
              >
                {errors?.occupation?.message}
              </ErrorMsg>
            )}
          </Col>
        )}
    </>
  );
};
